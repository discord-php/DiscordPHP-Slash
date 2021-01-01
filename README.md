# DiscordPHP-Slash

PHP server and client for Discord slash commands.

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

Now that you have registered commands, you can set up an HTTP server to listen for requests from Discord.

There are a few ways to set up an HTTP server to listen for requests:
- The built-in ReactPHP HTTP server.
- Using an external HTTP server such as Apache or nginx.
- Using the built-in ReactPHP HTTP server without HTTPS and using Apache or nginx as a reverse proxy (recommended).

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

This library only handles slash commands, and there is no support for any other interactions with Discord such as creating channels, sending other messages etc. You can easily combine the DiscordPHP library with this library to have a much larger collection of tools. All you must do is ensure both clients share the same ReactPHP event loop. In the future, there will be more integration between the libraries. Here is an example:

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

$discord->on('ready', function (Discord $discord) {
    // DiscordPHP is ready
});

$client->registerCommand('my_cool_command', function (Interaction $interaction, Choices $choices) use ($discord) {
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

## License

This software is licensed under the GNU General Public License v3.0 which can be viewed in the LICENSE.md file.

## Credits

- [David Cole](mailto:david.cole1340@gmail.com)
