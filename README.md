# Arma RCon Class for PHP (ARC)

ARC let's you easily send commands via  RCon to your Arma server. See "Games Supported" for a full list of supported Arma games.

## Games Supported
Please consider that mods normally don't change the BattlEye server settings, so this class also works for sending RCON commands  to servers running mods (e.g Altis Life, DayZ, Epoch etc.).

| App ID        | Game          | RCON Support       |
|---------------|---------------|:------------------:|
|233780         | Arma 3        | :white_check_mark: |
|33935          | Arma 2: Operation Arrowhead       | :white_check_mark: |
|33905          | Arma 2        | :white_check_mark: |

## Requirements
* PHP5
* PHP Socket Extension (http://php.net/manual/en/sockets.installation.php)

## Installation
To use ARC in your project, just inlcude `rcon.php` in your project.
```php
require_once '{PATH_TO_RCON.PHP}/rcon.php';
```

## Examples
After including `rcon.php` in your project, you need to create a new object, e.g:
```php
$rcon = new ARC("Your server IP", Port, "RCon password");
```
Then you can send commands with the `command()` function:
```php
$rcon->command("Say -1 hello!");
```
ARC can also send a heartbeat packet to the server. In order to do this, you need to enable it:
```php
$rcon = new ARC("Your server IP", Port, "RCon password", array (
        'heartbeat' => true,
    ));
```
ARC will throw Exceptions if anything goes wrong, so you could do a try-catch function:
```php
try 
{
    $rcon = new ARC("Your server IP", Port, "RCon password");
    $rcon->command("say -1 hello!");
} 
catch (Exception $e) 
{
    echo "Ups! Something went wrong.";
}
```
## Functions
ARC features a bunch of functions, which are predefined BattlEye commands (see https://community.bistudio.com/wiki/BattlEye):
* `command()`:  Sends any command to the server.
* `kick_player($player)`:  Kicks a player who is currently on the server.
* `say_global($message)`:  Sends a global message to all players.
* `say_player($player, $message)`:  Sends a message to a specific player.
* `load_scripts()`:  Loads the "scripts.txt" file without the need to restart the server.
* `max_ping($ping)`:  Changes the MaxPing value. If a player has a higher ping, he will be kicked from the server.
* `change_password($password)`:  Changes the RCon password.
* `load_bans()`:  (Re)load the BE ban list from bans.txt.
* `ban_player($player, $reason = "Banned", $time = 0)`:  Ban a player's BE GUID from the server. If time is not specified or 0, the ban will be permanent; if reason is not specified the player will be kicked with "Banned".
* `add_ban($player, $reason = "Banned", $time = 0)`:  Same as "ban_player", but allows to ban a player that is not currently on the server.
* `remove_ban($banid)`:  Removes a ban.
* `write_bans()`:  Removes expired bans from bans file.

## License
Code released under the MIT-License. See license file for more information.
