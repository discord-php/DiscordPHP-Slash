<?php

namespace Discord\Slash\Parts;

use Discord\InteractionResponseType;

/**
 * An interaction sent from Discord servers.
 * 
 * @property string $id
 * @property string $type
 * @property array $data
 * @property string $guild_id
 * @property string $channel_id
 * @property array $member
 * @property string $token
 * @property int $version
 */
class Interaction extends Part
{
    /**
     * The resolve function for the response promise.
     *
     * @var callable
     */
    private $resolve;

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
     * @param bool $source Whether to show the source message in chat.
     */
    public function acknowledge(bool $source = false)
    {
        ($this->resolve)([
            'type' => $source ? InteractionResponseType::ACKNOWLEDGE_WITH_SOURCE : InteractionResponseType::ACKNOWLEDGE,
        ]);
    }

    /**
     * Replies to the interaction with a message.
     *
     * @param string $content
     * @param bool $tts
     * @param array|null $embed
     * @param array|null $allowed_mentions
     * @param bool $source
     */
    public function reply(string $content, bool $tts = false, ?array $embed = null, ?array $allowed_mentions = null, bool $source = false)
    {
        $response = [
            'content' => $content,
            'tts' => $tts,
        ];

        if (! is_null($embed)) {
            $response['embed'] = $embed;
        }

        if (! is_null($allowed_mentions)) {
            $response['allowed_mentions'] = $allowed_mentions;
        }

        ($this->resolve)([
            'type' => $source ? InteractionResponseType::CHANNEL_MESSAGE_WITH_SOURCE : InteractionResponseType::CHANNEL_MESSAGE,
            'data' => $response,
        ]);
    }

    /**
     * Replies to the interaction with a message and shows the source message.
     * Alias for `reply()` with source = true.
     *
     * @param string $content
     * @param boolean $tts
     * @param array|null $embed
     * @param array|null $allowed_mentions
     */
    public function replyWithSource(string $content, bool $tts = false, ?array $embed = null, ?array $allowed_mentions = null)
    {
        $this->reply($content, $tts, $embed, $allowed_mentions, true);
    }
}