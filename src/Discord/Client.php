<?php

/*
 * This file is a part of the DiscordPHP-Slash project.
 *
 * Copyright (c) 2021 David Cole <david.cole1340@gmail.com>
 *
 * This source file is subject to the MIT license which is
 * bundled with this source code in the LICENSE.md file.
 */

namespace Discord\Slash;

use Discord\Discord;
use Discord\Http\Drivers\React;
use Discord\Http\Http;
use Discord\Interaction as DiscordInteraction;
use Discord\InteractionResponseType;
use Discord\InteractionType;
use Discord\Slash\Parts\Interaction;
use Discord\Slash\Parts\RegisteredCommand;
use Discord\WebSockets\Event;
use Exception;
use InvalidArgumentException;
use Kambo\Http\Message\Environment\Environment;
use Kambo\Http\Message\Factories\Environment\ServerRequestFactory;
use React\Http\Message\Response;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Http\Server as HttpServer;
use React\Promise\ExtendedPromiseInterface;
use React\Promise\Promise;
use React\Socket\Server as SocketServer;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Throwable;

/**
 * The Client class acts as an HTTP web server to handle requests from Discord when a command
 * is triggered. The class can also be used as a request handler by mocking a ServerRequestInterface
 * to allow it to be used with another webserver such as Apache or nginx.
 *
 * @author David Cole <david.cole1340@gmail.com>
 */
class Client
{
    const API_BASE_URI = 'https://discord.com/api/v8/';

    /**
     * Array of options for the client.
     *
     * @var array
     */
    private $options;

    /**
     * HTTP server.
     *
     * @var HttpServer
     */
    private $server;

    /**
     * Socket listening for connections.
     *
     * @var SocketServer
     */
    private $socket;

    /**
     * An array of registered commands.
     *
     * @var RegisteredCommand[]
     */
    private $commands;

    /**
     * ReactPHP event loop.
     *
     * @var LoopInterface
     */
    private $loop;

    /**
     * Logger for client.
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Optional Discord client.
     *
     * @var \Discord\Discord
     */
    private $discord;

    /**
     * Will we listen for gateway events or start HTTP server?
     *
     * @var bool
     */
    private $interactionsOverGateway = false;

    /**
     * HTTP client.
     *
     * @var Http
     */
    private $http;

    public function __construct(array $options = [])
    {
        $this->options = $this->resolveOptions($options);
        $this->loop = $this->options['loop'];
        $this->logger = $this->options['logger'];

        $this->loop->futureTick(function () {
            if ($this->interactionsOverGateway) {
                $this->logger->info('not starting http server - will wait for gateway events');

                return;
            }

            $this->registerServer();
        });
    }

    /**
     * Links the slash command client with a DiscordPHP client.
     * This will do a couple things:
     * - Interactions will be provided as "rich", meaning that the properties will be parts from DiscordPHP.
     * - If the `$interactionsOverGateway` parameter is true, the client will listen for interactions via
     *   gateway and the HTTP server will not be started.
     *
     * @param Discord $discord
     * @param bool    $interactionsOverGateway
     */
    public function linkDiscord(Discord $discord, bool $interactionsOverGateway = true)
    {
        $this->discord = $discord;
        $this->interactionsOverGateway = $interactionsOverGateway;

        if ($this->discord->getLoop() !== $this->loop) {
            throw new \RuntimeException('The Discord and slash client do not share the same event loop.');
        }

        $this->http = $discord->getHttp();

        if ($interactionsOverGateway) {
            $discord->on(Event::INTERACTION_CREATE, function ($interaction) {
                // possibly the laziest thing ive ever done - stdClass -> array
                $interaction = json_decode(json_encode($interaction), true);
                $interaction = new Interaction($interaction, $this->discord, $this->http, $this->options['application_id'] ?? null);

                $this->handleInteraction($interaction)->done(function ($response) use ($interaction) {
                    $this->handleGatewayInteractionResponse($response, $interaction);
                });
            });
        }
    }

    /**
     * Resolves the options for the client.
     *
     * @param array $options
     *
     * @return array
     */
    private function resolveOptions(array $options): array
    {
        $resolver = new OptionsResolver();

        $resolver
            ->setDefined([
                'uri',
                'logger',
                'loop',
                'public_key',
                'socket_options',
                'application_id',
                'token',
            ])
            ->setDefaults([
                'uri' => '0.0.0.0:80',
                'loop' => Factory::create(),
                'socket_options' => [],
                'public_key' => null,
                'application_id' => null,
                'token' => null,
            ]);

        $options = $resolver->resolve($options);

        if (! isset($options['logger'])) {
            $options['logger'] = (new Logger('DiscordPHP/Slash'))->pushHandler(new StreamHandler('php://stdout'));
        }

        return $options;
    }

    /**
     * Sets up the ReactPHP HTTP server.
     */
    private function registerServer()
    {
        // no uri => cgi/fpm
        if (is_null($this->options['uri'])) {
            $this->logger->info('running in CGI/FPM mode - follow up messages will not work');

            return;
        }

        $this->server = new HttpServer($this->getLoop(), function (ServerRequestInterface $request) {
            $identifier = sprintf('%s %s %s', $request->getMethod(), $request->getRequestTarget(), $request->getHeaderLine('User-Agent'));

            return $this->handleRequest($request)->then(function (Response $response) use ($identifier) {
                $this->logger->info("{$identifier} {$response->getStatusCode()} {$response->getReasonPhrase()}");

                return $response;
            }, function (Throwable $e) use ($identifier) {
                $this->logger->warning("{$identifier} {$e->getMessage()}");
            });
        });
        $this->socket = new SocketServer($this->options['uri'], $this->getLoop(), $this->options['socket_options']);
        $this->server->listen($this->socket);

        // already provided HTTP client through DiscordPHP
        if (! is_null($this->http)) {
            $this->logger->info('using DiscordPHP http client');

            return;
        }

        if (! isset($this->options['token'])) {
            $this->logger->warning('no token provided - http client will not work');
        }

        if (! isset($this->options['application_id'])) {
            $this->logger->warning('no application id provided - some methods may not work');
        }

        $this->http = new Http('Bot '.$this->options['token'], $this->loop, $this->logger, new React($this->loop, $this->options['socket_options']));
    }

    /**
     * Handles an HTTP request to the server.
     *
     * @param ServerRequestInterface $request
     */
    public function handleRequest(ServerRequestInterface $request)
    {
        if (! isset($this->options['public_key'])) {
            $this->logger->warning('A public key was not given to the slash client. Unable to validate request.');

            return \React\Promise\Resolve(new Response(401, [0], 'Not verified'));
        }

        // validate request with public key
        $signature = $request->getHeaderLine('X-Signature-Ed25519');
        $timestamp = $request->getHeaderLine('X-Signature-Timestamp');

        if (empty($signature) || empty($timestamp) || ! DiscordInteraction::verifyKey((string) $request->getBody(), $signature, $timestamp, $this->options['public_key'])) {
            return \React\Promise\Resolve(new Response(401, [0], 'Not verified'));
        }

        $interaction = new Interaction(json_decode($request->getBody(), true), $this->discord, $this->http, $this->options['application_id'] ?? null);

        $this->logger->info('received interaction', $interaction->jsonSerialize());

        return $this->handleInteraction($interaction)->then(function ($result) {
            $this->logger->info('responding to interaction', $result);

            return new Response(200, ['Content-Type' => 'application/json'], json_encode($result));
        });
    }

    /**
     * Handles an interaction from Discord.
     *
     * @param Interaction $interaction
     *
     * @return ExtendedPromiseInterface
     */
    private function handleInteraction(Interaction $interaction): ExtendedPromiseInterface
    {
        return new Promise(function ($resolve, $reject) use ($interaction) {
            switch ($interaction->type) {
                case InteractionType::PING:
                    return $resolve([
                        'type' => InteractionResponseType::PONG,
                    ]);
                case InteractionType::APPLICATION_COMMAND:
                    $interaction->setResolve($resolve);

                    return $this->handleApplicationCommand($interaction);
            }
        });
    }

    /**
     * Handles an application command interaction from Discord.
     *
     * @param Interaction $interaction
     */
    private function handleApplicationCommand(Interaction $interaction): void
    {
        $checkCommand = function ($command) use ($interaction, &$checkCommand) {
            if (isset($this->commands[$command['name']])) {
                if ($this->commands[$command['name']]->execute($command['options'] ?? [], $interaction)) {
                    return true;
                }
            }

            foreach ($command['options'] ?? [] as $option) {
                if ($checkCommand($option)) {
                    return true;
                }
            }
        };

        $checkCommand($interaction->data);
    }

    /**
     * Handles the user response from the command when the interaction
     * originates from the gateway.
     *
     * @param array       $response
     * @param Interaction $interaction
     */
    public function handleGatewayInteractionResponse(array $response, Interaction $interaction)
    {
        $this->discord->getHttpClient()->post("interactions/{$interaction->id}/{$interaction->token}/callback", $response)->done();
    }

    /**
     * Registeres a command with the client.
     *
     * @param string|array $name
     * @param callable     $callback
     *
     * @return RegisteredCommand
     */
    public function registerCommand($name, callable $callback = null): RegisteredCommand
    {
        if (is_array($name) && count($name) == 1) {
            $name = array_shift($name);
        }

        // registering base command
        if (! is_array($name) || count($name) == 1) {
            if (isset($this->commands[$name])) {
                throw new InvalidArgumentException("The command `{$name}` already exists.");
            }

            return $this->commands[$name] = new RegisteredCommand($name, $callback);
        }

        $baseCommand = array_shift($name);

        if (! isset($this->commands[$baseCommand])) {
            $this->registerCommand($baseCommand);
        }

        return $this->commands[$baseCommand]->addSubCommand($name, $callback);
    }

    /**
     * Runs the client on a CGI/FPM server.
     */
    public function runCgi()
    {
        if (empty($_SERVER['REMOTE_ADDR'])) {
            throw new Exception('The `runCgi()` method must only be called from PHP-CGI/FPM.');
        }

        if (! class_exists(Environment::class)) {
            throw new Exception('The `kambo/httpmessage` package must be installed to handle slash command interactions with a CGI/FPM server.');
        }

        $environment = new Environment($_SERVER, fopen('php://input', 'w+'), $_POST, $_COOKIE, $_FILES);
        $serverRequest = (new ServerRequestFactory())->create($environment);

        $this->handleRequest($serverRequest)->then(function (Response $response) {
            http_response_code($response->getStatusCode());
            echo (string) $response->getBody();
        });
    }

    /**
     * Starts the ReactPHP event loop.
     */
    public function run()
    {
        $this->getLoop()->run();
    }

    /**
     * Gets the ReactPHP event loop.
     *
     * @return LoopInterface
     */
    public function getLoop(): LoopInterface
    {
        return $this->loop;
    }
}
