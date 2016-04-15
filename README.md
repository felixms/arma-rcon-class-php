# Arma RCon Class (ARC) 2.0 for PHP 

ARC allows you connecting and sending commands easily via RCon to your ARMA game server. See "Supported Servers" for a list of supported games.
<br>
### ATTENTION: This version is currently in testing, do not use this in production!
## Supported Servers
Please consider that mods normally don't change the BattlEye server settings, so this class also works for sending RCON commands  to servers running mods (e.g Altis Life, DayZ, Epoch etc.).

| App ID        | Game          | RCON Support       |
|---------------|---------------|:------------------:|
|233780         | Arma 3        | :white_check_mark: |
|------         | DayZ Standalone*        | :white_check_mark: |
|33935          | Arma 2: Operation Arrowhead       | :white_check_mark: |
|33905          | Arma 2        | :white_check_mark: |
<br>
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
$ composer install
```
#### Without Composer
Just include the bootstrapper: `require_once 'ArmaRConClass/bootstrap.php';` 
<br>
<br>
## Examples
#### New in 2.0
```php
$rcon = new \Nizarii\ArmaRConClass\ARC();
```
Creating multiple connections with one ARC object
```php
$server1 = $rcon->connect("Your server IP 1", Port1, "RCon password 1");
$server2 = $rcon->connect("Your server IP 2", Port2, "RCon password 2");

$server1->disconnect();
$server2->disconnect();
```
Two new helpful functions 
```php
$players = $server1->getPlayersArray();
$bans = $server1->getBansArray();
```
Only possible with functions that don't return an answer from the server
```php
$server2
    ->sayGlobal('hello!')
    ->loadScripts()
    ;
    
```
#### Options
Currently `timeout_seconds` is the only option, more will be added in future versions.
```php
$rcon = new \Nizarii\ArmaRConClass\ARC(['timeout_seconds' => 1]); // 'timeout_seconds' is by default 1
```
*Sending a heartbeat packet is not possible anymore in version 2.0.*
<br>
<br>
## Functions
ARC features many functions to send BattlEye commands easier. After creating a new connections as explained above, you are able to use any of these functions:
* `command(string $command)`:  Sends any command to the BattlEye server and returns its answer as a string.
* `getPlayers()`:  Returns a list of all players online.
* `getPlayersArray()`:  Returns an array of all players online.
* `getMissions()`:  Returns a list of the available missions on the server.
* `getBans()`:  Returns a list of all BE server bans. 
* `getBansArray()`:  Returns an array of all BE server bans. 
* `kickPlayer(int $player, string $reason = 'Admin Kick')`:  Kicks a player who is currently on the server. 
* `sayGlobal(string $message)`:  Sends a global message to all players.
* `sayPlayer(int $player, string $message)`:  Sends a message to a specific player.
* `loadScripts()`:  Loads the "scripts.txt" file without the need to restart the server.
* `maxPing(int $ping)`:  Changes the MaxPing value. If a player has a higher ping, he will be kicked from the server.
* `changePassword(string $password)`:  Changes the RCon password.
* `loadBans()`:  (Re)load the BE ban list from bans.txt.
* `banPlayer(string $player, string $reason, int $time = 0)`:  Ban a player's BE GUID from the server. If time is not specified or 0, the ban will be permanent; if reason is not specified the player will be kicked with "Banned".
* `addBan(string $player, string $reason, int $time = 0)`:  Same as "ban_player", but allows to ban a player that is not currently on the server.
* `removeBan(int $banid)`:  Removes a ban.
* `writeBans()`:  Removes expired bans from bans file.

*See [here](https://community.bistudio.com/wiki/BattlEye "BattlEye Wiki") for more information about BattlEye*
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
