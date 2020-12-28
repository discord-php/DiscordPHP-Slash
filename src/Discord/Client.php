<?php

namespace Discord\Slash;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Http\Server as HttpServer;
use React\Promise\Deferred;
use React\Socket\Server as SocketServer;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The Client class acts as an HTTP web server to handle requests from Discord when a command
 * is triggered. The class can also be used as a request handler by mocking a ServerRequestInterface
 * to allow it to be used with another webserver such as Apache or nginx.
 *
 * @author David Cole <david.cole1340@gmail.com>
 */
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
            ])
            ->setDefaults([
                'uri' => '0.0.0.0:80',
                'loop' => Factory::create(),
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
