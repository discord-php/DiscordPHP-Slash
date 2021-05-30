<?php

/*
 * This file is a part of the DiscordPHP-Slash project.
 *
 * Copyright (c) 2021 David Cole <david.cole1340@gmail.com>
 *
 * This source file is subject to the MIT license which is
 * bundled with this source code in the LICENSE.md file.
 */

namespace Discord\Slash\Parts;

use Discord\Discord;
use Discord\Http\Http;
use Discord\InteractionResponseType;
use React\Promise\ExtendedPromiseInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * An interaction sent from Discord servers.
 *
 * @property string $id
 * @property string $type
 * @property array $data
 * @property string|null $guild_id
 * @property string $channel_id
 * @property \Discord\Parts\Member\Member|array $member
 * @property string $token
 * @property int $version
 *
 * The following properties are only present when a DiscordPHP client is given to
 * the slash command client:
 * @property \Discord\Parts\Guild\Guild|null $guild
 * @property \Discord\Parts\Channel\Channel|null $channel
 */
class Interaction extends Part
{
    /**
     * {@inheritdoc}
     */
    protected $fillable = ['id', 'type', 'data', 'guild_id', 'channel_id', 'member', 'token', 'version', 'guild', 'channel'];

    /**
     * The resolve function for the response promise.
     *
     * @var callable
     */
    private $resolve;

    /**
     * DiscordPHP instance.
     *
     * @var Discord
     */
    private $discord;

    /**
     * HTTP instance.
     *
     * @var Http
     */
    private $http;

    /**
     * Application ID.
     *
     * @var string
     */
    private $application_id;

    /**
     * {@inheritdoc}
     *
     * @param Discord $discord
     */
    public function __construct($attributes = [], Discord $discord = null, Http $http = null, string $application_id = null)
    {
        parent::__construct($attributes);
        $this->discord = $discord;
        $this->http = $http;
        $this->application_id = $application_id;

        if (is_null($this->http) && ! is_null($this->discord)) {
            $this->http = $this->discord->getHttpClient();
        }

        if (is_null($this->application_id) && ! is_null($this->discord)) {
            $this->application_id = $this->discord->application->id;
        }
    }

    /**
     * Sets the resolve function for the response promise.
     *
     * @param callable $resolve
     */
    public function setResolve(callable $resolve)
    {
        $this->resolve = $resolve;
    }

    /**
     * Acknowledges the interaction. At a bare minimum,
     * you should always acknowledge.
     */
    public function deferredChannelMessageWithSource()
    {
        ($this->resolve)([
            'type' => InteractionResponseType::DEFERRED_CHANNEL_MESSAGE_WITH_SOURCE,
            'data' => $response,
        ]);
        
    }

    /**
     * Replies to the interaction with a message.
     *
     * @see https://discord.com/developers/docs/interactions/slash-commands#interaction-response-interactionapplicationcommandcallbackdata
     *
     * @param string               $content          String content for the message. Required.
     * @param bool                 $tts              Whether the message should be text-to-speech.
     * @param array[]|Embed[]|null $embeds           An array of up to 10 embeds. Can also be an array of DiscordPHP embeds.
     * @param array|null           $allowed_mentions Allowed mentions object. See Discord developer docs.
     */
    public function reply(string $content, bool $tts = false, ?array $embeds = null, ?array $allowed_mentions = null)
    {
        $response = [
            'content' => $content,
            'tts' => $tts,
            'embeds' => $embeds,
            'allowed_mentions' => $allowed_mentions,
        ];

        ($this->resolve)([
            'type' => InteractionResponseType::CHANNEL_MESSAGE_WITH_SOURCE,
            'data' => $response,
        ]);
    }

    /**
     * Replies to the interaction with a message and shows the source message.
     * Alias for `reply()` with source = true.
     *
     * @see reply()
     *
     * @param string     $content
     * @param bool       $tts
     * @param array|null $embeds
     * @param array|null $allowed_mentions
     */
    public function replyWithSource(string $content, bool $tts = false, ?array $embeds = null, ?array $allowed_mentions = null)
    {
        $this->reply($content, $tts, $embeds, $allowed_mentions, true);
    }

    /**
     * Updates the original response to the interaction.
     * Must have already used `reply` or `replyWithSource`.
     *
     * Requires the ReactPHP event loop to be running and a token
     * to be passed to the slash client.
     * Also requires the slash client to be linked to a DiscordPHP client
     * OR an application ID given in the options array.
     *
     * @param string               $content          Content of the message.
     * @param array[]|Embed[]|null $embeds           An array of up to 10 embeds. Can also be an array of DiscordPHP embeds.
     * @param array|null           $allowed_mentions Allowed mentions object. See Discord developer docs.
     *
     * @return ExtendedPromiseInterface
     */
    public function updateInitialResponse(?string $content = null, ?array $embeds = null, ?array $allowed_mentions = null): ExtendedPromiseInterface
    {
        return $this->http->patch("webhooks/{$this->application_id}/{$this->token}/messages/@original", [
            'content' => $content,
            'embeds' => $embeds,
            'allowed_mentions' => $allowed_mentions,
        ]);
    }

    /**
     * Deletes the original response to the interaction.
     * Must have already used `reply` or `replyToSource`.
     *
     * Requires the ReactPHP event loop to be running and a token
     * to be passed to the slash client.
     * Also requires the slash client to be linked to a DiscordPHP client
     * OR an application ID given in the options array.
     *
     * @return ExtendedPromiseInterface
     */
    public function deleteInitialResponse(): ExtendedPromiseInterface
    {
        return $this->http->delete("webhooks/{$this->application_id}/{$this->token}/messages/@original");
    }

    /**
     * Creates a follow up message to the interaction.
     * Takes an array of options similar to a webhook - see the Discord developer documentation
     * for more information.
     * Returns an object representing the message in a promise.
     * To send files, use the `sendFollowUpFile` method.
     *
     * Requires the ReactPHP event loop to be running and a token
     * to be passed to the slash client.
     * Also requires the slash client to be linked to a DiscordPHP client
     * OR an application ID given in the options array.
     *
     * @see https://discord.com/developers/docs/resources/webhook#execute-webhook
     *
     * @param array $options
     *
     * @return ExtendedPromiseInterface
     */
    public function sendFollowUpMessage(array $options): ExtendedPromiseInterface
    {
        return $this->http->post("webhooks/{$this->application_id}/{$this->token}", $this->validateFollowUpMessage($options));
    }

    /**
     * Updates a follow up message.
     *
     * Requires the ReactPHP event loop to be running and a token
     * to be passed to the slash client.
     * Also requires the slash client to be linked to a DiscordPHP client
     * OR an application ID given in the options array.
     *
     * @param string               $message_id
     * @param string|null          $content
     * @param array[]|Embed[]|null $embeds
     * @param array|null           $allowed_mentions
     *
     * @return ExtendedPromiseInterface
     */
    public function updateFollowUpMessage(string $message_id, string $content = null, array $embeds = null, array $allowed_mentions = null)
    {
        return $this->http->patch("webhooks/{$this->application_id}/{$this->token}/messages/{$message_id}", [
            'content' => $content,
            'embeds' => $embeds,
            'allowed_mentions' => $allowed_mentions,
        ]);
    }

    /**
     * Deletes a follow up message.
     *
     * Requires the ReactPHP event loop to be running and a token
     * to be passed to the slash client.
     * Also requires the slash client to be linked to a DiscordPHP client
     * OR an application ID given in the options array.
     *
     * @param string $message_id
     *
     * @return ExtendedPromiseInterface
     */
    public function deleteFollowUpMessage(string $message_id)
    {
        return $this->http->delete("webhooks/{$this->application_id}/{$this->token}/messages/{$message_id}");
    }
    /**
     * Validates a follow up message content.
     *
     * @param array $options
     *
     * @return array
     */
    private function validateFollowUpMessage(array $options)
    {
        $resolver = new OptionsResolver();

        $resolver
        ->setDefined([
            'content', 'username', 'avatar_url',
            'tts', 'embeds', 'allowed_mentions',
        ])
        ->setAllowedTypes('content', 'string')
        ->setAllowedTypes('username', 'string')
        ->setAllowedTypes('avatar_url', 'string')
        ->setAllowedTypes('tts', 'bool')
        ->setAllowedTypes('embeds', 'array')
        ->setAllowedTypes('allowed_mentions', 'array');

        $options = $resolver->resolve($options);

        if (! isset($options['content']) && ! isset($options['embeds'])) {
            throw new \RuntimeException('One of content, embeds is required.');
        }

        return $options;
    }

    /**
     * Returns the guild attribute.
     *
     * @return \Discord\Parts\Guild\Guild
     */
    private function guild()
    {
        return $this->discord->guilds->get('id', $this->guild_id);
    }

    /**
     * Returns the channel attribute.
     *
     * @return \Discord\Parts\Channel\Channel
     */
    private function channel()
    {
        return $this->guild->channels->get('id', $this->channel_id);
    }

    /**
     * Returns the member attribute.
     *
     * @return \Discord\Parts\User\Member
     */
    private function member()
    {
        return $this->guild->members->get('id', $this->attributes['member']['user']['id']);
    }

    /**
     * {@inheritdoc}
     */
    public function __get(string $key)
    {
        if (! is_null($this->discord) && is_callable([$this, $key])) {
            return $this->{$key}();
        }

        return parent::__get($key);
    }
}
