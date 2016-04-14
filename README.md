# Arma RCon Class (ARC) 2.0 for PHP 

ARC allows you connecting and sending commands easily via RCon to your ARMA game server. See "Supported Servers" for a list of supported games.
<br>
### ATTENTION: This version is currently in testing, do not use this in production!

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
$ composer install
```
#### Without Composer
Just include the bootstrapper: `require_once 'ArmaRConClass/bootstrap.php';` 
<br>
<br>
## Examples
#### New in 2.0
```php
$rcon = new \Nizarii\ArmaRConClass\ARC(['timeout_sec' => 1]); // 'timeout_sec' is by default 1

// Create multiple connections with one ARC object
$server1 = $rcon->connnect("Your server IP 1", Port1, "RCon password 1");
$server2 = $rcon->connnect("Your server IP 2", Port2, "RCon password 2");

$result = $server2->command("Say -1 hello!");

// Only possible with functions that don't return an answer from the server
$server2
    ->sayGlobal('hello!')
    ->loadScripts()
    ;
    
```
Sending a heartbeat packet is not possible anymore in version 2.0.
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
