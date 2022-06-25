# DiscordPHP-Slash

PHP server and client for Discord slash commands. Please read the [Discord slash command documentation](https://discord.com/developers/docs/interactions/slash-commands) before using this library.

> **If you are already using [DiscordPHP](https://github.com/discord-php/DiscordPHP) v7+ you DO NOT need this DiscordPHP-Slash library**.
> Read more here: https://github.com/discord-php/DiscordPHP/wiki/Slash-Command

## Warning

Discord slash commands are still in beta. Expect the way these commands work to change at any time without notice. Same goes for this library.

## Requirements

- PHP >=7.3
- Composer

## Installation

```
$ composer require discord-php/slash
```

## Usage

There are two "clients" in the library:
- `Discord\Slash\RegisterClient` used for registering commands with Discord.
- `Discord\Slash\Client` used for listening for HTTP requests and responding.

### `Discord\Slash\RegisterClient`

You should read up on how commands are registered in the [Discord Developer Documentation](https://discord.com/developers/docs/interactions/slash-commands#registering-a-command), specifically the `options` array when creating and updating commands.

```php
<?php

include 'vendor/autoload.php';

use Discord\Slash\RegisterClient;

$client = new RegisterClient('your-bot-token-here');

/// GETTING COMMANDS

// gets a list of all GLOBAL comamnds (not guild-specific)
$commands = $client->getCommands();
// gets a list of all guild-specific commands to the given guild
$guildCommands = $client->getCommands('guild_id_here');
// gets a specific command with command id - if you are getting a guild-specific command you must provide a guild id
$command = $client->getCommand('command_id', 'optionally_guild_id');

/// CREATING COMMANDS

// creates a global command
$command = $client->createGlobalCommand('command_name', 'command_description', [
    // optional array of options
]);

// creates a guild specific command
$command = $client->createGuildSpecificCommand('guild_id', 'command_name', 'command_description', [
    // optional array of options
]);

/// UPDATING COMMANDS

// change the command name etc.....
$command->name = 'newcommandname';
$client->updateCommand($command);

/// DELETING COMMANDS

$client->deleteCommand($command);
```

### `Discord\Slash\Client`

There are two ways to set up the slash client:
- Webhook method
- Gateway method (deprecated)

Please read both sections as both have important information and both have advantages/disadvantages.

#### Webhook method

Now that you have registered commands, you can set up an HTTP server to listen for requests from Discord.

There are a few ways to set up an HTTP server to listen for requests:
- The built-in ReactPHP HTTP server.
- Using the built-in ReactPHP HTTP server without HTTPS and using Apache or nginx as a reverse proxy (recommended).
- Using an external HTTP server such as Apache or nginx.

Whatever path you choose, the server **must** be protected with HTTPS - Discord will not accept regular HTTP.

At the moment for testing, I am running the built-in ReactPHP HTTP server on port `8080` with no HTTPS. I then have an Apache2 web server *with HTTPS* that acts as a reverse proxy to the ReactPHP server. An example of setting this up on Linux is below.

Setting up a basic `Client`:

```php
<?php

include 'vendor/autoload.php';

use Discord\Slash\Client;
use Discord\Slash\Parts\Interaction;
use Discord\Slash\Parts\Choices;

$client = new Client([
    // required options
    'public_key' => 'your_public_key_from_discord_here',

    // optional options, defaults are shown
    'uri' => '0.0.0.0:80', // if you want the client to listen on a different URI
    'logger' => $logger, // different logger, default will write to stdout
    'loop' => $loop, // reactphp event loop, default creates a new loop
    'socket_options' => [], // options to pass to the react/socket instance, default empty array
]);

// register a command `/hello`
$client->registerCommand('hello', function (Interaction $interaction, Choices $choices) {
    // do some cool stuff here
    // good idea to var_dump interaction and choices to see what they contain

    // once finished, you MUST either acknowledge or reply to a message
    $interaction->acknowledge(); // acknowledges the message, doesn't show source message
    $interaction->acknowledge(true); // acknowledges the message and shows the source message

    // to reply to the message
    $interaction->reply('Hello, world!'); // replies to the message, doesn't show source message
    $interaction->replyWithSource('Hello, world!'); // replies to the message and shows the source message

    // the `reply` methods take 4 parameters: content, tts, embed and allowed_mentions
    // all but content are optional.
    // read the discord developer documentation to see what to pass to these options:
    // https://discord.com/developers/docs/resources/channel#create-message
});

// starts the ReactPHP event loop
$client->run();
```

Please note that you must always acknowledge the interaction within 3 seconds, otherwise Discord will cancel the interaction.
If you are going to do something that takes time, call the `acknowledge()` function and then add a follow up message using `sendFollowUpMessage()` when ready.

This library only handles slash commands, and there is no support for any other interactions with Discord such as creating channels, sending other messages etc. You can easily combine the DiscordPHP library with this library to have a much larger collection of tools. All you must do is ensure both clients share the same ReactPHP event loop. Here is an example:

```php
<?php

include 'vendor/autoload.php';

// make sure you have included DiscordPHP into your project - `composer require team-reflex/discord-php`

use Discord\Discord;
use Discord\Slash\Client;
use Discord\Slash\Parts\Interaction;
use Discord\Slash\Parts\Choices;

$discord = new Discord([
    'token' => '##################',
]);

$client = new Client([
    'public_key' => '???????????????',
    'loop' =>  $discord->getLoop(),
]);

$client->linkDiscord($discord, false); // false signifies that we still want to use the HTTP server - default is true, which will use gateway

$discord->on('ready', function (Discord $discord) {
    // DiscordPHP is ready
});

$client->registerCommand('my_cool_command', function (Interaction $interaction, Choices $choices) use ($discord) {
    // there are a couple fields in $interaction that will return DiscordPHP parts:
    $interaction->guild;
    $interaction->channel;
    $interaction->member;

    // if you don't link DiscordPHP, it will simply return raw arrays

    $discord->guilds->get('id', 'coolguild')->members->ban(); // do something ?
    $interaction->acknowledge();
});

$discord->run();
```

### Running behing PHP-CGI/PHP-FPM

To run behind CGI/FPM and a webserver, the `kambo/httpmessage` package is required:

```sh
$ composer require kambo/httpmessage
```

The syntax is then exactly the same as if you were running with the ReactPHP http server, except for the last line:

```php
<?php

include 'vendor/autoload.php';

use Discord\Slash\Client;

$client = new Client([
    'public_key' => '???????',
    'uri' => null, // note the null uri - signifies to not register the socket
]);

// register your commands like normal
$client->registerCommand(...);

// note the difference here - runCgi instead of run
$client->runCgi();
```

Do note that the regular DiscordPHP client will not run on CGI or FPM, so your mileage may vary.

### Setting up Apache2 as a reverse proxy

Assuming you already have Apache2 installed and the SSL certificates on your server:

1. Enable the required Apache mods:
```shell
$ sudo a2enmod proxy
$ sudo a2enmod proxy_http
$ sudo a2enmod ssl
```
2. Create a new site or modify the existing default site to listen on port `443`:
```sh
$ vim /etc/apache2/sites-available/000-default.conf # default site

# change contents to the following
<VirtualHost *:443> # listen on 443
        ProxyPreserveHost       On                              # preserve the host header from Discord
        ProxyPass               /       http://127.0.0.1:8080/  # pass-through to the HTTP server on port 8080
        ProxyPassReverse        /       http://127.0.0.1:8080/

        SSLEngine               On                              # enable SSL
        SSLCertificateFile      /path/to/ssl/cert.crt           # change to your cert path
        SSLCertificateKeyFile   /path/to/ssl/cert.key           # change to your key path
</VirtualHost>
```
3. Restart apache - the code below works on Debian-based systems:
```shell
$ sudo service apache2 restart
```

#### Gateway method (Deprecated)

> Starting with [DiscordPHP](https://github.com/discord-php/DiscordPHP) v7.0.0, slash commands are now integrated into the main library. **You no longer need this DiscordPHP-Slash library anymore**!
> Read more here: https://github.com/discord-php/DiscordPHP/blob/master/V7_CONVERSION.md#slash-commands

The client can connect with a regular [DiscordPHP](https://github.com/discord-php/DiscordPHP) client to listen for interactions over gateway.
To use this method, make sure there is no interactions endpoint set in your Discord developer application.

Make sure you have included DiscordPHP into your project (at the time of writing, only DiscordPHP 6.x is supported):

```sh
$ composer require team-reflex/discord-php
```

You can then create both clients and link them:

```php
<?php

include 'vendor/autoload.php';

use Discord\Discord;
use Discord\Slash\Client;

$discord = new Discord([
    'token' => 'abcd.efdgh.asdas',
]);

$client = new Client([
    'loop' => $discord->getLoop(), // Discord and Client MUST share event loops
]);

$client->linkDiscord($discord);

$client->registerCommand(...);

$discord->run();
```

The gateway method is much easier to set up as you do not have to worry about SSL certificates.

## License

This software is licensed under the MIT license which can be viewed in the LICENSE.md file.

## Credits

- [David Cole](mailto:david.cole1340@gmail.com)
