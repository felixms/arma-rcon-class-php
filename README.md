# Arma RCon Class (ARC) 2.1 for PHP 
[![Codacy](https://img.shields.io/codacy/f42d50a9693b4febb34fab3f68315365.svg)](https://www.codacy.com/app/nizari/arma-rcon-class-php)
[![Packagist Version](https://img.shields.io/packagist/v/nizarii/arma-rcon-class.svg)](https://packagist.org/packages/nizarii/arma-rcon-class)
[![GitHub license](https://img.shields.io/github/license/nizarii/arma-rcon-class-php.svg)](https://github.com/Nizarii/arma-rcon-class-php/)

ARC is a lightweight PHP class, which allows you connecting and sending commands easily via RCon to your ARMA game server. See "Supported Servers" for a list of supported games.
<br>
<br>
## Supported Servers
| App ID        | Game          | RCON Support       |
|---------------|---------------|:------------------:|
|233780         | Arma 3        | :white_check_mark: |
|33935          | Arma 2: Operation Arrowhead       | :white_check_mark: |
|33905          | Arma 2        | :white_check_mark: |
<br>
<br>
## Requirements
ARC requires **PHP 5.4** or higher, nothing more!
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
$ composer install
```
#### Without Composer
Just include ARC in your project: `require_once 'arc.php';` 
<br>
<br>
## Examples
#### Getting started
After installing ARC, you need to create a new object. It will automatically create a new connection to the server and login:
```php
$rcon = new \Nizarii\ARC("Your server IP", Port, "RCon password");
```
Then you are able to send commands with the `command()` function:
```php
$rcon->command("Say -1 hello!"); // To say something in global chat, you may use 'sayGlobal()', see 'Functions'
```
ARC will throw Exceptions if anything goes wrong, so you can do a try-catch function:
```php
try 
{
    $rcon = new \Nizarii\ARC("Your server IP", Port, "RCon password");
       
    $array = $rcon->getPlayersArray();
    
    $rcon
        ->sayGlobal('test')
        ->kickPlayer(1, 'test')
        ->sayPlayer(0, 'test')
        ->close()
    ;
    
    $rcon->getBans(); // Throws exception, because the connection was closed
} 
catch (Exception $e) 
{
    echo "Ups! Something went wrong: ".$e->getMessage();
}
```
#### Options
ARC can send a heartbeat packet to the server. In order to do this, you need to enable it:
```php
$rcon = new \Nizarii\ARC("Your server IP", Port, "RCon password", [
    'heartbeat' => true, // must be a boolean
]);
```
Another option is `timeout_seconds`, which sets a timeout value on the connection:
```php
$rcon = new \Nizarii\ARC("Your server IP", Port, "RCon password", [
    'timeout_sec'      => 1, // must be an integer, by default 1 second
]);
    
$rcon->writeBans(); 
```
<br>
## Functions
ARC features many functions to send BattlEye commands easier. After creating a new connections as explained above, you are able to use any of these functions:
* `command(string $command)`:  Sends any command to the BattlEye server and returns its answer as a string.
* `getPlayers()`:  Returns a list of all players online.
* `getPlayersArray()`:  Returns an array of all players online.
* `getMissions()`:  Returns a list of the available missions on the server.
* `getBans()`:  Returns a list of all BE server bans.
* `getBansArray()`:  Returns an array of all server bans. 
* `kickPlayer(int $player, string $reason = 'Admin Kick')`:  Kicks a player who is currently on the server. 
* `sayGlobal(string $message)`:  Sends a global message to all players.
* `sayPlayer(int $player, string $message)`:  Sends a message to a specific player.
* `loadScripts()`:  Loads the "scripts.txt" file without the need to restart the server.
* `maxPing(int $ping)`:  Changes the MaxPing value. If a player has a higher ping, he will be kicked from the server.
* `changePassword(string $password)`:  Changes the RCon password.
* `loadBans()`:  (Re)load the BE ban list from bans.txt.
* `banPlayer(string $player, string $reason, int $time = 0)`:  Ban a player's BE GUID from the server. If time is not specified or 0, the ban will be permanent; If reason is not specified the player will be kicked with "Banned".
* `addBan(string $player, string $reason, int $time = 0)`:  Same as "banPlayer", but allows to ban a player that is not currently on the server.
* `removeBan(int $banid)`:  Removes a ban.
* `writeBans()`:  Removes expired bans from bans file.
* `getBEServerVersion()`: Gets the current version of the BE server.

*See [here](https://community.bistudio.com/wiki/BattlEye "BattlEye Wiki") for more information about BattlEye*
<br>
<br>
## License

ARC is licensed under the MIT License. See `LICENSE`-file for further information.
