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

use Discord\InteractionResponseFlags;
use Discord\InteractionResponseType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * An interaction sent from Discord servers.
 *
 * @see https://discord.com/developers/docs/interactions/receiving-and-responding#interactions
 *
 * @property string      $id             ID of the interaction.
 * @property string      $application_id ID of the application the interaction is for.
 * @property int         $type           Type of interaction.
 * @property object|null $data           Data associated with the interaction.
 * @property string|null $guild_id       ID of the guild the interaction was sent from.
 * @property string|null $channel_id     ID of the channel the interaction was sent from.
 * @property object|null $member         Member who invoked the interaction.
 * @property object|null $user           User who invoked the interaction.
 * @property string      $token          Continuation token for responding to the interaction.
 * @property int         $version        Version of interaction.
 * @property object|null $message        Message that triggered the interactions, when triggered from message components.
 * @property string|null $locale         The selected language of the invoking user.
 * @property string|null $guild_locale   The guild's preferred locale, if invoked in a guild.
 */
class Interaction extends Part
{
    /**
     * @inheritdoc
     */
    protected $fillable = [
        'id',
        'application_id',
        'type',
        'data',
        'guild_id',
        'channel_id',
        'member',
        'user',
        'token',
        'version',
        'message',
        'locale',
        'guild_locale',
    ];

    /**
     * The resolve function for the response promise.
     *
     * @var callable
     */
    protected $resolve;

    /**
     * Whether we have responded to the interaction yet.
     *
     * @var bool
     */
    protected $responded = false;

    /**
     * HTTP instance.
     *
     * @var \Discord\Http\Http
     */
    private $http;

    /**
     * {@inheritdoc}
     *
     * @param array           $attributes
     * @param \Discord\Http\Http|null $http
     */
    public function __construct($attributes = [], $http = null)
    {
        parent::__construct($attributes);
        $this->http = $http;
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
     *
     * @see https://discord.com/developers/docs/interactions/receiving-and-responding#responding-to-an-interaction
     *
     * @param bool $ephemeral Whether the acknowledge should be ephemeral.
     */
    public function acknowledge(bool $ephemeral = false)
    {
        ($this->resolve)([
            'type' => InteractionResponseType::DEFERRED_CHANNEL_MESSAGE_WITH_SOURCE,
            'data' => $ephemeral ? ['flags' => InteractionResponseFlags::EPHEMERAL] : []
        ]);

        $this->responded = true;
    }

    /**
     * Replies to the interaction with a message.
     *
     * @see https://discord.com/developers/docs/interactions/receiving-and-responding#create-interaction-response
     *
     * @param string|array $content          String content for the message or the array of message data (with other arguments ignored).
     * @param bool         $tts              Whether the message should be text-to-speech.
     * @param array|null   $embeds           An array of up to 10 embeds.
     * @param array|null   $allowed_mentions Allowed mentions object. See Discord developer docs.
     * @param int|null     $flags            Set to 64 to make your response ephemeral.
     * @param array|null   $components       Message components.
     * @param array|null   $attachments      Attachment objects with filename and description.
     */
    public function reply($content, bool $tts = false, ?array $embeds = null, ?array $allowed_mentions = null, ?int $flags = null, ?array $components = null, ?array $attachments = null)
    {
        if (is_string($content)) {
            $response = [
                'content' => $content,
                'tts' => $tts,
                'embeds' => $embeds,
                'allowed_mentions' => $allowed_mentions,
                'flags' => $flags,
                'components' => $components,
                'attachments' => $attachments,
            ];
        } else {
            $response = $content;
        }

        ($this->resolve)([
            'type' => InteractionResponseType::CHANNEL_MESSAGE_WITH_SOURCE,
            'data' => $response,
        ]);

        $this->responded = true;
    }

    /**
     * Updates the original response to the interaction.
     * Must have already used `Interaction::reply()`.
     *
     * Requires the ReactPHP event loop to be running and a token to be passed to the slash client.
     *
     * @see https://discord.com/developers/docs/interactions/receiving-and-responding#edit-original-interaction-response
     *
     * @param string|array $content          String content for the message or the array of message data (with other arguments ignored).
     * @param array|null   $embeds           An array of up to 10 embeds.
     * @param array|null   $allowed_mentions Allowed mentions object. See Discord developer docs.
     * @param array|null   $components       Message components.
     * @param array|null   $attachments      Attachment objects to keep with filename and description.
     *
     * @return \React\Promise\ExtendedPromiseInterface
     */
    public function updateOriginalResponse($content = null, ?array $embeds = null, ?array $allowed_mentions = null, ?array $components = null, ?array $attachments = null): \React\Promise\ExtendedPromiseInterface
    {
        if (is_string($content)) {
            $response = [
                'content' => $content,
                'embeds' => $embeds,
                'allowed_mentions' => $allowed_mentions,
                'components' => $components,
                'attachments' => $attachments,
            ];
        } else {
            $response = $content;
        }

        return $this->http->patch(\Discord\Http\Endpoint::bind(\Discord\Http\Endpoint::ORIGINAL_INTERACTION_RESPONSE, $this->application_id, $this->token), $response);
    }

    /**
     * @deprecated 2.0.0 Interaction::updateOriginalResponse()
     */
    public function updateInitialResponse(...$args)
    {
        return $this->updateOriginalResponse(...$args);
    }

    /**
     * Deletes the original response to the interaction.
     * Must have already used `Interaction::reply()`.
     *
     * Requires the ReactPHP event loop to be running and a token to be passed to the slash client.
     *
     * @see https://discord.com/developers/docs/interactions/receiving-and-responding#delete-original-interaction-response
     *
     * @return \React\Promise\ExtendedPromiseInterface
     */
    public function deleteOriginalResponse(): \React\Promise\ExtendedPromiseInterface
    {
        return $this->http->delete(\Discord\Http\Endpoint::bind(\Discord\Http\Endpoint::ORIGINAL_INTERACTION_RESPONSE, $this->application_id, $this->token));
    }

    /**
     * @deprecated v2.0.0 Interaction::deleteOriginalResponse()
     */
    public function deleteInitialResponse()
    {
        return $this->deleteOriginalResponse();
    }

    /**
     * Creates a follow up message to the interaction.
     * Takes an array of options similar to a webhook - see the Discord developer documentation
     * for more information.
     * Returns an object representing the message in a promise.
     * To send files, use the `sendFollowUpFile` method.
     *
     * Requires the ReactPHP event loop to be running and a token to be passed to the slash client.
     *
     * @see https://discord.com/developers/docs/interactions/receiving-and-responding#create-followup-message
     *
     * @param array $options
     *
     * @return \React\Promise\ExtendedPromiseInterface
     */
    public function sendFollowUpMessage(array $options): \React\Promise\ExtendedPromiseInterface
    {
        return $this->http->post(\Discord\Http\Endpoint::bind(\Discord\Http\Endpoint::CREATE_INTERACTION_FOLLOW_UP, $this->application_id, $this->token), $this->validateFollowUpMessage($options));
    }

    /**
     * Updates a follow up message.
     *
     * Requires the ReactPHP event loop to be running and a token to be passed to the slash client.
     *
     * @see https://discord.com/developers/docs/interactions/receiving-and-responding#edit-followup-message
     *
     * @param string|int        $message_id
     * @param string|array|null $content          String content for the message or the array of message data (with other arguments ignored).
     * @param array|null        $embeds           An array of up to 10 embeds.
     * @param array|null        $allowed_mentions Allowed mentions object. See Discord developer docs.
     * @param array|null        $components       Message components.
     * @param array|null        $attachments      Attachment objects to keep with filename and description.
     *
     * @return \React\Promise\ExtendedPromiseInterface
     */
    public function updateFollowUpMessage(string $message_id, $content = null, array $embeds = null, array $allowed_mentions = null, ?array $components = null, ?array $attachments = null): \React\Promise\ExtendedPromiseInterface
    {
        if (is_string($content)) {
            $message = [
                'content' => $content,
                'embeds' => $embeds,
                'allowed_mentions' => $allowed_mentions,
                'components' => $components,
                'attachments' => $attachments,
            ];
        } else {
            $message = $content;
        }

        return $this->http->patch(\Discord\Http\Endpoint::bind(\Discord\Http\Endpoint::INTERACTION_FOLLOW_UP, $this->application_id, $this->token, $message_id), $message);
    }

    /**
     * Deletes a follow up message.
     *
     * Requires the ReactPHP event loop to be running and a token to be passed to the slash client.
     *
     * @see https://discord.com/developers/docs/interactions/receiving-and-responding#delete-followup-message
     *
     * @param string|int $message_id
     *
     * @throws \RuntimeException
     *
     * @return \React\Promise\ExtendedPromiseInterface
     */
    public function deleteFollowUpMessage($message_id)
    {
        return $this->http->delete(\Discord\Http\Endpoint::bind(\Discord\Http\Endpoint::INTERACTION_FOLLOW_UP, $this->application_id, $this->token, $message_id));
    }
    /**
     * Validates a follow up message content.
     *
     * @see https://discord.com/developers/docs/resources/webhook#execute-webhook-jsonform-params
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
                'content',
                'tts',
                'embeds',
                'allowed_mentions',
                'components',
                'attachments',
                'flags',
            ])
            ->setAllowedTypes('content', 'string')
            ->setAllowedTypes('tts', 'bool')
            ->setAllowedTypes('embeds', 'array')
            ->setAllowedTypes('allowed_mentions', 'array')
            ->setAllowedTypes('components', 'array')
            ->setAllowedTypes('attachments', 'array')
            ->setAllowedTypes('flags', 'int');

        $options = $resolver->resolve($options);

        if (! isset($options['content']) && ! isset($options['embeds'])) {
            throw new \RuntimeException('One of content, embeds is required.');
        }

        return $options;
    }
}
