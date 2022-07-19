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

/**
 * Represents a command registered on the Discord servers.
 *
 * @author David Cole <david.cole1340@gmail.com>
 *
 * @see https://discord.com/developers/docs/interactions/application-commands#application-command-object-application-command-structure
 *
 * @property string         $id                         The unique identifier of the command.
 * @property int            $type                       The type of the command, defaults 1 if not set.
 * @property string         $application_id             The unique identifier of the parent Application that made the command, if made by one.
 * @property string|null    $guild_id                   The unique identifier of the guild that the command belongs to. Null if global.
 * @property string         $name                       1-32 character name of the command.
 * @property ?string[]|null $name_localizations         Localization dictionary for the name field. Values follow the same restrictions as name.
 * @property string         $description                1-100 character description for CHAT_INPUT commands, empty string for USER and MESSAGE commands.
 * @property ?string[]|null $description_localizations  Localization dictionary for the description field. Values follow the same restrictions as description.
 * @property array|null     $options                    The parameters for the command, max 25. Only for Slash command (CHAT_INPUT).
 * @property ?string        $default_member_permissions Set of permissions represented as a bit set.
 * @property bool|null      $dm_permission              Indicates whether the command is available in DMs with the app, only for globally-scoped commands. By default, commands are visible.
 * @property string         $version                    Autoincrementing version identifier updated during substantial record changes.
 */
class Command extends Part
{
    /** Slash commands; a text-based command that shows up when a user types / */
    public const CHAT_INPUT = 1;

    /**
     * @inheritdoc
     */
    protected $fillable = [
        'id',
        'type',
        'application_id',
        'guild_id',
        'name',
        'name_localizations',
        'description',
        'description_localizations',
        'options',
        'default_member_permissions',
        'dm_permission',
        'default_permission',
        'version',
    ];

    /**
     * Returns a formatted mention of the command.
     *
     * @return string A formatted mention of the command.
     */
    public function __toString()
    {
        return "</{$this->name}:{$this->id}>";
    }
}
