<?php

/*
 * This file is a part of the DiscordPHP-Slash project.
 *
 * Copyright (c) 2020-present David Cole <david.cole1340@gmail.com>
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this source code in the LICENSE.md file.
 */

namespace Discord\Slash\Enums;

/**
 * @link https://discord.com/developers/docs/interactions/slash-commands#applicationcommandoptiontype
 * @author David Cole <david.cole1340@gmail.com>
 */
final class ApplicationCommandOptionType
{
    public const SUB_COMMAND = 1;
    public const SUB_COMMAND_GROUP = 2;
    public const STRING = 3;
    public const INTEGER = 4;
    public const BOOLEAN = 5;
    public const USER = 6;
    public const CHANNEL = 7;
    public const ROLE = 8;
}
