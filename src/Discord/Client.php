<?php

namespace Discord\SlashCommands;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use GuzzleHttp\Client as HttpClient;
use React\Http\Server as HttpServer;
use React\Promise\Deferred;
use React\Socket\Server as SocketServer;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Client
{
    const API_BASE_URI = "https://discord.com/api/v8/";

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
     * HTTP client.
     *
     * @var Http
     */
    private $http;

    /**
     * Socket listening for connections.
     *
     * @var SocketServer
     */
    private $socket;

    public function __construct(array $options = [])
    {
        $this->options = $this->resolveOptions($options);
        $this->registerServer();
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
                'token',
            ])
            ->setDefaults([
                'uri' => '0.0.0.0:80',
                'loop' => Factory::create(),
            ]);

        $options = $resolver->resolve($options);

        if (! isset($options['logger'])) {
            $options['logger'] = (new Logger('DiscordPHP/Slash'))->pushHandler(new StreamHandler('php://stdout'));
        }

        if (! isset($options['token'])) {
            $options['logger']->warning('no token given - unable to register commands');
        } else {
            $this->http = new HttpClient([
                'base_uri' => self::API_BASE_URI,
                'headers' => [
                    'User-Agent' => $this->getUserAgent(),
                    'Authorization' => 'Bot '.$options['token'],
                ],
            ]);
        }

        return $options;
    }

    /**
     * Sets up the ReactPHP HTTP server.
     */
    private function registerServer()
    {
        $this->server = new HttpServer($this->getLoop(), [$this, 'handleRequest']);

        $this->socket = new SocketServer($this->options['uri'], $this->getLoop());
        $this->server->listen($this->socket);
    }

    public function handleRequest(ServerRequestInterface $request)
    {
        $deferred = new Deferred();

        return $deferred->promise();
    }

    /**
     * Returns the User-Agent of the application.
     *
     * @return string
     */
    private function getUserAgent()
    {
        return "DiscordBot (https://github.com/davidcole1340/DiscordPHP-Slash, v0.0.1)";
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
        return $this->options['loop'];
    }
}
