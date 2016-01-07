# Arma RCon Class for PHP (ARC)
[![Packagist Version](https://img.shields.io/packagist/v/nizarii/arma-rcon-class.svg)](https://packagist.org/packages/nizarii/arma-rcon-class)

ARC is a lightweight PHP class, which let's you easily send commands via  RCon to your Arma server. See "Supported Server" for a full list of supported Arma games.
<br>
<br>
## Supported Servers
Please consider that mods normally don't change the BattlEye server settings, so this class also works for sending RCON commands  to servers running mods (e.g Altis Life, DayZ, Epoch etc.).

| App ID        | Game          | RCON Support       |
|---------------|---------------|:------------------:|
|233780         | Arma 3        | :white_check_mark: |
|------         | DayZ Standalone*        | :white_check_mark: |
|33935          | Arma 2: Operation Arrowhead       | :white_check_mark: |
|33905          | Arma 2        | :white_check_mark: |
*RCon is only usable on Privates Hives of DayZ SA Servers (thanks to JaG-v2)
<br>
## Requirements
ARC requires **PHP 5.3** or higher, nothing more!
<br>
<br>
## Installation 
#### Via Composer
If you haven't already, download Composer
```shell
$ curl -s http://getcomposer.org/installer | php
```
Now require and install ARC
```shell
$ composer require nizarii/arma-rcon-class
$ php composer.phar install
```
#### Without Composer
Just include ARC in your project: `require_once 'ArmaRConClass/rcon.php';` 
<br>
<br>
## Examples
#### Getting started
After including `rcon.php` in your project, you need to create a new object. It will automatically create a new connection to the server, so you don't need to call `connect()`, e.g.:
```php
$rcon = new \Nizarii\ArmaRConClass\ARC("Your server IP", Port, "RCon password");
```
Then you are able to send commands with the `command()` function:
```php
$rcon->command("Say -1 hello!"); // To say something in global chat, you may use 'say_global()', see 'Functions'
```
ARC will throw Exceptions if anything goes wrong, so you could do a try-catch function:
```php
try 
{
    $rcon = new \Nizarii\ARC("Your server IP", Port, "RCon password");
    
    if ($rcon->say_player(0, "hey!"))  // say_player returns true/false, see functions list
    {
       echo "success!";
    } 
    else
    {
       echo "failed!";
    }
} 
catch (Exception $e) 
{
    echo "Ups! Something went wrong: ".$e->getMessage();
}
```
#### Options
ARC can send a heartbeat packet to the server. In order to do this, you need to enable it:
```php
$rcon = new \Nizarii\ARC("Your server IP", Port, "RCon password", array (
        'heartbeat' => true,
    ));
```
Another option is `timeout_seconds`, which sets a timeout value on the connection:
```php
$rcon = new \Nizarii\ARC("Your server IP", Port, "RCon password", array (
        'send_heartbeat'       => true,
        'timeout_seconds'      => 1, // by default 1 second
    ));
    
$rcon->write_bans(); 
```
<br>
## Functions
ARC features many functions to send BattlEye commands easier:
* `command(string $command)`:  Sends any command to the BattlEye server and returns its answer as a string. Note: Returns false if sending failed.
* `get_players()`:  Returns a list of all players online. Note: Returns false if sending failed.
* `get_missions()`:  Returns a list of the available missions on the server. Note: Returns false if sending failed.
* `get_bans()`:  Returns a list of all BE server bans. Note: Returns false if sending failed.
* `kick_player(int $player, string $reason = 'Admin Kick')`:  Kicks a player who is currently on the server. *
* `say_global(string $message)`:  Sends a global message to all players.*
* `say_player(int $player, string $message)`:  Sends a message to a specific player.*
* `load_scripts()`:  Loads the "scripts.txt" file without the need to restart the server.*
* `max_ping(int $ping)`:  Changes the MaxPing value. If a player has a higher ping, he will be kicked from the server.*
* `change_password(string $password)`:  Changes the RCon password.*
* `load_bans()`:  (Re)load the BE ban list from bans.txt.*
* `ban_player(string $player, string $reason, int $time = 0)`:  Ban a player's BE GUID from the server. If time is not specified or 0, the ban will be permanent; if reason is not specified the player will be kicked with "Banned".*
* `add_ban(string $player, string $reason, int $time = 0)`:  Same as "ban_player", but allows to ban a player that is not currently on the server.*
* `remove_ban(int $banid)`:  Removes a ban.*
* `write_bans()`:  Removes expired bans from bans file.*
* `disconnect()`:  Closes the existing connection to the BattlEye server manually, sending commands after calling this function is not possible.
* `connect(string $ServerIP = "", int $ServerPort = "", string $RConPassword = "")`:  Creates a new connection to the server and closes the existing one. Note: It's not required to call disconnect() before.

*These functions will return true if the execution was successful and false if it failed.
<br>
See [here](https://community.bistudio.com/wiki/BattlEye "BattlEye Wiki") for more information about BattlEye
<br>
<br>
## License

The MIT License (MIT)

Copyright (c) 2015 Felix Schäfer

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
