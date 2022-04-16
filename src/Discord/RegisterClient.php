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

use Discord\Slash\Enums\ApplicationCommandOptionType;
use Discord\Slash\Parts\Command;
use Discord\Slash\Parts\Part;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use ReflectionClass;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * This class is used to register commands with Discord.
 * You should only need to use this class once, from thereon you can use the Client
 * class to start a webserver to listen for slash command requests.
 *
 * @author David Cole <david.cole1340@gmail.com>
 */
class RegisterClient
{
    /**
     * Discord application.
     *
     * @var object
     */
    private $application;

    /**
     * HTTP client.
     *
     * @var GuzzleClient
     */
    private $http;

    /**
     * HTTP client constructor.
     *
     * @param string $token
     */
    public function __construct(string $token)
    {
        $this->http = new GuzzleClient([
            'base_uri' => 'https://discord.com/api/v8/',
            'headers' => [
                'User-Agent' => $this->getUserAgent(),
                'Authorization' => 'Bot '.$token,
            ],
        ]);

        $this->application = new Part($this->request('GET', 'oauth2/applications/@me'));
    }

    /**
     * Returns a list of commands.
     *
     * @param string|null $guild_id The guild ID to get commands for.
     *
     * @return Command[]
     */
    public function getCommands(?string $guild_id = null)
    {
        $endpoint = "applications/{$this->application->id}";

        if (! is_null($guild_id)) {
            $endpoint .= "/guilds/{$guild_id}";
        }

        $response = $this->request('GET', $endpoint.'/commands');
        $commands = [];

        foreach ($response as $command) {
            if (! is_null($guild_id)) {
                $command['guild_id'] = $guild_id;
            }

            $commands[] = new Command($command);
        }

        return $commands;
    }

    /**
     * Tries to get a command.
     *
     * @param string $command_id
     * @param string $guild_id
     *
     * @return Command
     */
    public function getCommand(string $command_id, ?string $guild_id = null)
    {
        $endpoint = "applications/{$this->application->id}";

        if (! is_null($guild_id)) {
            $endpoint .= "/guilds/{$guild_id}";
        }

        $response = $this->request('GET', "{$endpoint}/commands/{$command_id}");

        if (! is_null($guild_id)) {
            $response['guild_id'] = $guild_id;
        }

        return new Command($response);
    }

    /**
     * Creates a global command.
     *
     * @param string $name
     * @param string $description
     * @param array  $options
     *
     * @return Command
     */
    public function createGlobalCommand(string $name, string $description, array $options = [])
    {
        foreach ($options as $key => $option) {
            $options[$key] = $this->resolveApplicationCommandOption($option);
        }

        $response = $this->request('POST', "applications/{$this->application->id}/commands", [
            'name' => $name,
            'description' => $description,
            'options' => $options,
        ]);

        return new Command($response);
    }

    /**
     * Creates a guild-specific command.
     *
     * @param string $guild_id
     * @param string $name
     * @param string $description
     * @param array  $options
     *
     * @return Command
     */
    public function createGuildSpecificCommand(string $guild_id, string $name, string $description, array $options = [])
    {
        foreach ($options as $key => $option) {
            $options[$key] = $this->resolveApplicationCommandOption($option);
        }

        $response = $this->request('POST', "applications/{$this->application->id}/guilds/{$guild_id}/commands", [
            'name' => $name,
            'description' => $description,
            'options' => $options,
        ]);

        $response['guild_id'] = $guild_id;

        return new Command($response);
    }

    /**
     * Updates the Discord servers with the changes done to the given command.
     *
     * @param Command $command
     *
     * @return Command
     */
    public function updateCommand(Command $command)
    {
        $raw = $command->getAttributes();

        foreach ($raw['options'] ?? [] as $key => $option) {
            $raw['options'][$key] = $this->resolveApplicationCommandOption($option);
        }

        $endpoint = "applications/{$this->application->id}";

        if ($command->guild_id) {
            unset($raw['guild_id']);
            $endpoint .= "/guilds/{$command->guild_id}";
        }

        $this->request('PATCH', "{$endpoint}/commands/{$command->id}", $raw);

        return $command;
    }

    /**
     * Deletes a command from the Discord servers.
     *
     * @param Command $command
     */
    public function deleteCommand(Command $command)
    {
        $endpoint = "applications/{$this->application->id}";

        if ($command->guild_id) {
            $endpoint .= "/guilds/{$command->guild_id}";
        }

        $this->request('DELETE', "{$endpoint}/commands/{$command->id}");
    }

    /**
     * Resolves an `ApplicationCommandOption` part.
     *
     * @param array $options
     *
     * @return array
     */
    private function resolveApplicationCommandOption(array $options): array
    {
        $resolver = new OptionsResolver();

        $resolver
        ->setDefined([
            'type',
            'name',
            'description',
            'default',
            'required',
            'choices',
            'options',
        ])
        ->setAllowedTypes('type', 'int')
        ->setAllowedValues('type', array_values((new ReflectionClass(ApplicationCommandOptionType::class))->getConstants()))
        ->setAllowedTypes('name', 'string')
        ->setAllowedTypes('description', 'string')
        ->setAllowedTypes('default', 'bool')
        ->setAllowedTypes('required', 'bool')
        ->setAllowedTypes('choices', 'array')
        ->setAllowedTypes('options', 'array');

        $options = $resolver->resolve($options);

        foreach ($options['choices'] ?? [] as $key => $choice) {
            $options['choices'][$key] = $this->resolveApplicationCommandOptionChoice($choice);
        }

        foreach ($options['options'] ?? [] as $key => $option) {
            $options['options'][$key] = $this->resolveApplicationCommandOption($option);
        }

        return $options;
    }

    /**
     * Resolves an `ApplicationCommandOption` part.
     *
     * @param array $options
     *
     * @return array
     */
    private function resolveApplicationCommandOptionChoice(array $options): array
    {
        $resolver = new OptionsResolver();

        $resolver
        ->setDefined([
            'name',
            'value',
        ])
        ->setAllowedTypes('name', 'string')
        ->setAllowedTypes('value', ['string', 'int']);

        return $resolver->resolve($options);
    }

    /**
     * Runs an HTTP request and decodes the JSON.
     *
     * @param string $method
     * @param string $endpoint
     * @param array  $content
     *
     * @return array
     */
    private function request(string $method, string $endpoint, ?array $content = null)
    {
        $options = [];

        if (! is_null($content)) {
            $options['json'] = $content;
        }

        try {
            $response = $this->http->request($method, $endpoint, $options);
        } catch (RequestException $e) {
            switch ($e->getResponse()->getStatusCode()) {
            case 429:
                $resetAfter = (float) $e->getResponse()->getheaderLine('X-RateLimit-Reset-After');
                usleep($resetAfter * 1000000);

                return $this->request($method, $endpoint, $content);
            default:
                throw $e;
            }
        }

        return json_decode($response->getBody(), true);
    }
    /**
     * Returns the User-Agent of the application.
     *
     * @return string
     */
    private function getUserAgent()
    {
        return 'DiscordBot (https://github.com/davidcole1340/DiscordPHP-Slash, v2.0.0)';
    }
}
