<?php

/**
 * ARC is a lightweight class, helping you to send commands to your ARMA server via RCon.
 *
 * @author    Felix Schäfer
 * @copyright 2017 Felix Schäfer
 * @license   MIT-License
 * @link      https://github.com/Nizarii/arma-rcon-php-class Github repository of ARC
 * @version   2.2
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace Nizarii;

class ARC
{
    /**
     * @var array Options for ARC stored in an array
     */
    private $options = [
        'timeoutSec'    => 1,
        'autosaveBans'  => false,
        'debug'         => false
    ];

    /**
     * @var string Server IP of the BattlEye server
     */
    private $serverIP;

    /**
     * @var int Specific port of the BattlEye server
     */
    private $serverPort;

    /**
     * @var string Required password for authenticating
     */
    private $rconPassword;

    /**
     * @var resource Socket for sending commands
     */
    private $socket;

    /**
     * @var bool Status of the connection
     */
    private $disconnected = true;

    /**
     * @var string Head of the message, which was sent to the server
     */
    private $head;

    /**
     * @var int Sequence number and also a helper to end loops.
     */
    private $end = 0; // required to remember the sequence.

    /**
     * Class constructor
     *
     * @param string $serverIP      IP of the Arma server
     * @param integer $serverPort   Port of the Arma server
     * @param string $RConPassword  RCon password required by BattlEye
     * @param array $options        Options array of ARC
     *
     * @throws \Exception if wrong parameter types were passed to the function
     */
    public function __construct($serverIP, $RConPassword, $serverPort = 2302, array $options = array())
    {
        if (!is_int($serverPort) || !is_string($RConPassword) || !is_string($serverIP)) {
            throw new \Exception('Wrong constructor parameter type(s)!');
        }

        $this->serverIP = $serverIP;
        $this->serverPort = $serverPort;
        $this->rconPassword = $RConPassword;
        $this->options = array_merge($this->options, $options);

        $this->checkOptionTypes();
        $this->checkForDeprecatedOptions();

        $this->connect();
    }

    /**
     * Class destructor
     */
    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Closes the connection
     */
    public function disconnect()
    {
        if ($this->disconnected) {
            return;
        }
        fclose($this->socket);

        $this->socket = null;
        $this->disconnected = true;
    }

    /**
     * Creates a connection to the server
     *
     * @throws \Exception if creating the socket fails
     */
    private function connect()
    {
        if (!$this->disconnected) {
            $this->disconnect();
        }

        $this->socket = @fsockopen("udp://$this->serverIP", $this->serverPort, $errno, $errstr, $this->options['timeoutSec']);
        if (!$this->socket) {
            throw new \Exception('Failed to create socket!');
        }

        stream_set_timeout($this->socket, $this->options['timeoutSec']);
        stream_set_blocking($this->socket, true);

        $this->authorize();
        $this->disconnected = false;
    }

    /**
     * Closes the current connection and creates a new one
     */
    public function reconnect()
    {
        if (!$this->disconnected) {
            $this->disconnect();
        }

        $this->connect();
        return $this;
    }

    /**
     * Checks if ARC's option array contains any deprecated options
     */
    private function checkForDeprecatedOptions()
    {
        if (array_key_exists('timeout_sec', $this->options)) {
            @trigger_error("The 'timeout_sec' option is deprecated since version 2.1.2 and will be removed in 3.0. Use 'timeoutSec' instead.", E_USER_DEPRECATED);
            $this->options['timeoutSec'] = $this->options['timeout_sec'];
        }
        if (array_key_exists('heartbeat', $this->options) || array_key_exists('sendHeartbeat', $this->options)) {
            @trigger_error("Sending a heartbeat packet is deprecated since version 2.2.", E_USER_DEPRECATED);
        }
    }

    /**
     * Validate all option types
     */
    private function checkOptionTypes()
    {
        if (!is_int($this->options['timeoutSec'])) {
            throw new \Exception(
                sprintf("Expected option 'timeoutSec' to be integer, got %s", gettype($this->options['timeoutSec']))
            );
        }
        if (!is_bool($this->options['autosaveBans'])) {
            throw new \Exception(
                sprintf("Expected option 'autosaveBans' to be boolean, got %s", gettype($this->options['autosaveBans']))
            );
        }
        if (!is_bool($this->options['debug'])) {
            throw new \Exception(
                sprintf("Expected option 'debug' to be boolean, got %s", gettype($this->options['debug']))
            );
        }
    }

    /**
     * Sends the login data to the server in order to send commands later
     *
     * @throws \Exception if login fails (due to a wrong password or port)
     */
    private function authorize()
    {
        $sent = $this->writeToSocket($this->getLoginMessage());
        if ($sent === false) {
            throw new \Exception('Failed to send login!');
        }

        $result = fread($this->socket, 16);
        if (@ord($result[strlen($result)-1]) == 0) {
            throw new \Exception('Login failed, wrong password or wrong port!');
        }
    }

    /**
     * Receives the answer form the server
     *
     * @return string Any answer from the server, except the log-in message
     */
    protected function getResponse()
    {
        $get = function() {
            return substr(fread($this->socket, 102400), strlen($this->head));
        };
        
        $output = '';
        do {
            $answer = $get();
            while (strpos($answer, 'RCon admin') !== false) {
                $answer = $get();
            }

            $output .= $answer;
        } while (!empty($answer));

        return $output;
    }

    /**
     * The heart of this class - this function actually sends the RCon command
     *
     * @param string $command The command sent to the server
     *
     * @throws \Exception if the connection is closed
     * @throws \Exception if sending the command failed
     *
     * @return bool Whether sending the command was successful or not
     */
    protected function send($command)
    {
        if ($this->disconnected) {
            throw new \Exception('Failed to send command, because the connection is closed!');
        }
        $msgCRC = $this->getMsgCRC($command);
        $head = 'BE'.chr(hexdec($msgCRC[0])).chr(hexdec($msgCRC[1])).chr(hexdec($msgCRC[2])).chr(hexdec($msgCRC[3])).chr(hexdec('ff')).chr(hexdec('01')).chr(hexdec(sprintf('%01b', 0)));

        $msg = $head.$command;
        $this->head = $head;

        if ($this->writeToSocket($msg) === false) {
            throw new \Exception('Failed to send command!');
        }
    }

    /**
     * Writes the given message to the socket
     *
     * @param string $message Message which will be written to the socket
     *
     * @return int
     */
    private function writeToSocket($message)
    {
        return fwrite($this->socket, $message);
    }

    /**
     * Generates the password's CRC32 data
     *
     * @return string
     */
    private function getAuthCRC()
    {
        $authCRC = sprintf('%x', crc32(chr(255).chr(00).trim($this->rconPassword)));
        $authCRC = array(substr($authCRC,-2,2), substr($authCRC,-4,2), substr($authCRC,-6,2), substr($authCRC,0,2));

        return $authCRC;
    }

    /**
     * Generates the message's CRC32 data
     *
     * @param string $command The message which will be prepared for being sent to the server
     *
     * @return string Message which can be sent to the server
     */
    private function getMsgCRC($command)
    {
        $msgCRC = sprintf('%x', crc32(chr(255).chr(01).chr(hexdec(sprintf('%01b', 0))).$command));
        $msgCRC = array(substr($msgCRC,-2,2),substr($msgCRC,-4,2),substr($msgCRC,-6,2),substr($msgCRC,0,2));

        return $msgCRC;
    }

    /**
     * Generates the login message
     *
     * @return string The message for authenticating in, containing the RCon password
     */
    private function getLoginMessage()
    {
        $authCRC = $this->getAuthCRC();

        $loginMsg = 'BE'.chr(hexdec($authCRC[0])).chr(hexdec($authCRC[1])).chr(hexdec($authCRC[2])).chr(hexdec($authCRC[3]));
        $loginMsg .= chr(hexdec('ff')).chr(hexdec('00')).$this->rconPassword;

        return $loginMsg;
    }

    /**
     * Returns the socket used by ARC, might be null if connection is closed
     *
     * @return resource
     */
    public function getSocket()
    {
        return $this->socket;
    }

    /**
     * Sends a custom command to the server
     *
     * @param string $command Command which will be sent to the server
     *
     * @throws \Exception if wrong parameter types were passed to the function
     *
     * @return string Response from the server
     */
    public function command($command)
    {
        if (!is_string($command)) {
            throw new \Exception('Wrong parameter type!');
        }

        $this->send($command);
        return $this->getResponse();
    }

    /**
     * Executes multiple commands
     *
     * @param array $commands Commands to be executed
     */
    public function commands(array $commands)
    {
        foreach ($commands as $command) {
            if (!is_string($command)) {
                continue;
            }
            $this->command($command);
        }
    }

    /**
     * Kicks a player who is currently on the server
     *
     * @param string $reason  Message displayed why the player is kicked
     * @param integer $player The player who should be kicked

     * @throws \Exception if wrong parameter types were passed to the function
     *
     * @return ARC
     */
    public function kickPlayer($player, $reason = 'Admin Kick')
    {
        if (!is_int($player) && !is_string($player)) {
            throw new \Exception(
                sprintf('Expected parameter 1 to be string or integer, got %s', gettype($player))
            );
        }
        if (!is_string($reason)) {
            throw new \Exception(
                sprintf('Expected parameter 2 to be string, got %s', gettype($reason))
            );
        }

        $this->send("kick $player $reason");
        $this->reconnect();

        return $this;
    }

    /**
     * Sends a global message to all players
     *
     * @param string $message The message which will be shown to all players

     * @throws \Exception if wrong parameter types were passed to the function
     *
     * @return ARC
     */
    public function sayGlobal($message)
    {
        if (!is_string($message)) {
            throw new \Exception(
                sprintf('Expected parameter 1 to be string, got %s', gettype($message))
            );
        }

        $this->send("Say -1 $message");
        return $this;
    }

    /**
     * Sends a message to a specific player
     *
     * @param integer $player Player who will be sent the message to
     * @param string $message Message for the player

     * @throws \Exception if wrong parameter types were passed to the function
     *
     * @return ARC
     */
    public function sayPlayer($player, $message)
    {
        if (!is_int($player) || !is_string($message)) {
            throw new \Exception('Wrong parameter type(s)!');
        }

        $this->send("Say $player $message");
        return $this;
    }

    /**
     * Loads the "scripts.txt" file without the need to restart the server
     *
     * @return ARC
     */
    public function loadScripts()
    {
        $this->send('loadScripts');
        return $this;
    }

    /**
     * Changes the MaxPing value. If a player has a higher ping, he will be kicked from the server
     *
     * @param integer $ping The value for the 'MaxPing' BattlEye server setting
     *
     * @throws \Exception if wrong parameter types were passed to the function
     *
     * @return ARC
     */
    public function maxPing($ping)
    {
        if (!is_int($ping)) {
            throw new \Exception(
                sprintf('Expected parameter 1 to be integer, got %s', gettype($ping))
            );
        }

        $this->send("MaxPing $ping");
        return $this;
    }

    /**
     * Changes the RCon password
     *
     * @param string $password The new password
     *
     * @throws \Exception if wrong parameter types were passed to the function
     *
     * @return ARC
     */
    public function changePassword($password)
    {
        if (!is_string($password)) {
            throw new \Exception(
                sprintf('Expected parameter 1 to be string, got %s', gettype($password))
            );
        }

        $this->send("RConPassword $password");
        return $this;
    }

    /**
     * (Re)load the BE ban list from bans.txt
     *
     * @return ARC
     */
    public function loadBans()
    {
        $this->send('loadBans');
        return $this;
    }

    /**
     * Gets a list of all players currently on the server
     *
     * @return string The list of all players on the server
     */
    public function getPlayers()
    {
        $this->send('players');
        $result = $this->getResponse();
        
        $this->reconnect();
        return $result;
    }

    /**
     * Gets a list of all players currently on the server as an array
     *
     * @author nerdalertdk (https://github.com/nerdalertdk)
     * @link https://github.com/Nizarii/arma-rcon-class-php/issues/4 The related GitHub Issue
     *
     * @throws \Exception if sending the command failed
     *
     * @return array The array containing all players being currently on the server
     */
    public function getPlayersArray()
    {
        $playersRaw = $this->getPlayers();

        $players = $this->cleanList($playersRaw);
        preg_match_all("#(\d+)\s+(\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d+\b)\s+(\d+)\s+([0-9a-fA-F]+)\(\w+\)\s([\S ]+)$#im", $players, $str);
        
        return $this->formatList($str);
    }

    /**
     * Gets a list of all bans
     *
     * @throws \Exception if sending the command failed
     *
     * @return string List containing the missions
     */
    public function getMissions()
    {
        $this->send('missions');
        return $this->getResponse();
    }

    /**
     * Ban a player's BE GUID from the server. If time is not specified or 0, the ban will be permanent;.
     * If reason is not specified the player will be kicked with the message "Banned".
     *
     * @param integer $player Player who will be banned
     * @param string $reason  Reason why the player is banned
     * @param integer $time   How long the player is banned in minutes (0 = permanent)

     * @throws \Exception if wrong parameter types were passed to the function
     *
     * @return ARC
     */
    public function banPlayer($player, $reason = 'Banned', $time = 0)
    {
        if (!is_string($player) && !is_int($player)) {
            throw new \Exception(
                sprintf('Expected parameter 1 to be integer or string, got %s', gettype($player))
            );
        }

        if (!is_string($reason) || !is_int($time)) {
            throw new \Exception('Wrong parameter type(s)!');
        }

        $this->send("ban $player $time $reason");
        $this->reconnect();
        
        if ($this->options['autosaveBans']) {
            $this->writeBans();
        }

        return $this;
    }

    /**
     * Same as "banPlayer", but allows to ban a player that is not currently on the server
     *
     * @param integer $player Player who will be banned
     * @param string $reason  Reason why the player is banned
     * @param integer $time   How long the player is banned in minutes (0 = permanent)
     *
     * @throws \Exception if wrong parameter types were passed to the function
     *
     * @return ARC
     */
    public function addBan($player, $reason = 'Banned', $time = 0)
    {
        if (!is_string($player) || !is_string($reason) || !is_int($time)) {
            throw new \Exception('Wrong parameter type(s)!');
        }

        $this->send("addBan $player $time $reason");
        
        if ($this->options['autosaveBans']) {
            $this->writeBans();
        }
        
        return $this;
    }

    /**
     * Removes a ban
     *
     * @param integer $banId Ban who will be removed
     *
     * @throws \Exception if wrong parameter types were passed to the function
     *
     * @return ARC
     */
    public function removeBan($banId)
    {
        if (!is_int($banId)) {
            throw new \Exception(
                sprintf('Expected parameter 1 to be integer, got %s', gettype($banId))
            );
        }

        $this->send("removeBan $banId");
       
        if ($this->options['autosaveBans']) {
            $this->writeBans();
        }
        
        return $this;
    }

    /**
     * Gets an array of all bans
     *
     * @author nerdalertdk (https://github.com/nerdalertdk)
     * @link https://github.com/Nizarii/arma-rcon-class-php/issues/4
     *
     * @return array The array containing all bans
     */
    public function getBansArray()
    {
        $bansRaw = $this->getBans();
        $bans = $this->cleanList($bansRaw);

        preg_match_all("#(\d+)\s+([0-9a-fA-F]+)\s([perm|\d]+)\s([\S ]+)$#im", $bans, $str);
        return $this->formatList($str);
    }

    /**
     * Gets a list of all bans
     *
     * @return string The response from the server
     */
    public function getBans()
    {
        $this->send('bans');
        return $this->getResponse();
    }

    /**
     * Removes expired bans from bans file
     *
     * @return ARC
     */
    public function writeBans()
    {
        $this->send('writeBans');
        return $this;
    }

    /**
     * Gets the current version of the BE server
     *
     * @return string The BE server version
     */
    public function getBEServerVersion()
    {
        $this->send('version');
        return $this->getResponse();
    }

    /**
    * Get socket and continue streaming and disconnect after looping.
    *
    * @author steffalon (https://github.com/steffalon)
    * @link https://github.com/schaeferfelix/arma-rcon-class-php/issues/30 issue part 1
    * @link https://github.com/schaeferfelix/arma-rcon-class-php/issues/31 issue part 2
    *
    * @param integer $loop      Number of loops through this funtion. By default, (-1) for no ending.
    *
    * @return boolean
    */
    public function socketLoopClose($loop = -1)
    {
        if ($this->end !== null) {
            $loop = $this->end + $loop;
        }
        while ($this->end !== $loop) {
            $msg = fread($this->socket, 9000);
            if ($this->options['debug']) {
                echo preg_replace("/\r|\n/", "", substr($msg, 9)).PHP_EOL;
            }
            $timeout = stream_get_meta_data($this->socket);
            if ($timeout['timed_out']) {
                $this->keepAlive();
            } else {
                $this->end = $this->readPackage($msg);
            }
        }
        $this->end = 0;
        $this->disconnect();
        return true; // Completed
    }

    /**
    * Get socket and continue streaming and don't disconnect after looping.
    *
    * @author steffalon (https://github.com/steffalon)
    * @link https://github.com/schaeferfelix/arma-rcon-class-php/issues/30 issue part 1
    * @link https://github.com/schaeferfelix/arma-rcon-class-php/issues/31 issue part 2
    *
    * @param integer $loop      Number of loops through this funtion. By default, (-1) for no ending.
    *
    * @return boolean
    */
    public function socketLoop($loop = -1)
    {
        if ($this->end !== null) {
            $loop = $this->end + $loop;
        }
        while ($this->end !== $loop) {
            $msg = fread($this->socket, 9000);
            if ($this->options['debug']) {
                echo preg_replace("/\r|\n/", "", substr($msg, 9)).PHP_EOL;
            }
            $timeout = stream_get_meta_data($this->socket);
            if ($timeout['timed_out']) {
                $this->keepAlive();
            } else {
                $this->end = $this->readPackage($msg);
            }
        }
        return true; // Completed
    }

    /**
    * Reads what kind of package it is.
    *
    * @author steffalon (https://github.com/steffalon)
    * @link https://github.com/schaeferfelix/arma-rcon-class-php/issues/30 issue part 1
    * @link https://github.com/schaeferfelix/arma-rcon-class-php/issues/31 issue part 2
    *
    * @param string $msg   message received from BE with unreadable header.
    *
    * @throws \Exception by invalid BERCon login details.
    *
    * @return integer
    */
    private function readPackage($msg)
    {
        $responseCode = unpack('H*', $msg); // Make message usefull for battleye packet by unpacking it to hexbyte.
        $responseCode = str_split(substr($responseCode[1], 12), 2); // Get important hexbytes.
        switch ($responseCode[1]) { // See https://www.battleye.com/downloads/BERConProtocol.txt for packet info.
            case "00": // Login WILL ONLY HAPPEN IF socketLoopClose() got called and is done.
                if ($responseCode[2] == "01") { // Login successful.
                    if ($this->options['debug']) {
                        echo "Accepted BERCon login.".PHP_EOL;
                    }
                    $this->authorize();
                } else { // Otherwise $responseCode[2] == "0x00" (Login failed)
                    throw new \Exception('Invalid BERCon login details. This process is getting stopped!');
                }
                break;
            case "01": // Send commands by this client.
                if (count($responseCode) == 3) {
                    break;
                }
                if ($responseCode[3] !== "00") { // This package is small.
                    if ($this->options['debug']) {
                        echo "This is a small package.".PHP_EOL;
                    }
                } else {
                    if ($this->options['debug']) { //This package is multi-packet.
                        echo "Multi-packet.".PHP_EOL;
                    }
                    // if ($this->options['debug']) var_dump($responseCode); //Useful developer information.
                    //      if ($responseCode[5] == "00") {
                    //      $getAmount = $responseCode[4];
                    //      if ($this->options['debug']) var_dump($getAmount);
                    // }
                }
                break;
            case "02": // Acknowledge as client.
                return $this->acknowledge($this->end);
                break;
        }
    }

    /**
    * Acknowledge the data and add +1 to sequence.
    *
    * @author steffalon (https://github.com/steffalon)
    * @link https://github.com/schaeferfelix/arma-rcon-class-php/issues/30 issue part 1
    * @link https://github.com/schaeferfelix/arma-rcon-class-php/issues/31 issue part 2
    *
    * @param integer $int   Sequence number. Makes a new header with that number.
    *
    * @throws \Exception if failed to send a command
    *
    * @return integer
    */
    private function acknowledge($int)
    {
        if ($this->options['debug']) {
            echo "Acknowledge!".PHP_EOL;
        }
        $needBuffer = chr(hexdec('ff')).chr(hexdec('02')).chr(hexdec(sprintf('%2X', $int)));
        $needBuffer = hash("crc32b", $needBuffer);
        $needBuffer = str_split($needBuffer, 2);
        $needBuffer = array_reverse($needBuffer);
        $statusmsg = "BE".chr(hexdec($needBuffer[0])).chr(hexdec($needBuffer[1])).chr(hexdec($needBuffer[2])).chr(hexdec($needBuffer[3]));
        $statusmsg .= chr(hexdec('ff')).chr(hexdec('02')).chr(hexdec(sprintf('%2X', $int)));
        if ($this->writeToSocket($statusmsg) === false) {
            throw new \Exception('Failed to send command!');
        }
        return ++$int; // Sequence +1
    }

    /**
    * Keep the stream alive. Send package to BE server. Use this function before 45 seconds.
    *
    * @author steffalon (https://github.com/steffalon)
    * @link https://github.com/schaeferfelix/arma-rcon-class-php/issues/30 issue part 1
    * @link https://github.com/schaeferfelix/arma-rcon-class-php/issues/31 issue part 2
    *
    * @throws \Exception if failed to send a command
    *
    * @return boolean
    */
    private function keepAlive()
    {
        if ($this->options['debug']) {
            echo '--Keep connection alive--'.PHP_EOL;
        }
        $keepalive = "BE".chr(hexdec("be")).chr(hexdec("dc")).chr(hexdec("c2")).chr(hexdec("58"));
        $keepalive .= chr(hexdec('ff')).chr(hexdec('01')).chr(hexdec(sprintf('00')));
        if ($this->writeToSocket($keepalive) === false) {
            throw new \Exception('Failed to send command!');
            return false; // Failed
        }
        return true; // Completed
    }

    /**
     * Converts BE text "array" list to array
     *
     * @author nerdalertdk (https://github.com/nerdalertdk)
     * @link https://github.com/Nizarii/arma-rcon-class-php/issues/4 The related Github issue
     *
     * @param $str array
     *
     * @return array
     */
    private function formatList($str)
    {
        // Remove first array
        array_shift($str);
        // Create return array
        $result = array();

        // Loop true the main arrays, each holding a value
        foreach($str as $key => $value) {
            // Combines each main value into new array
            foreach($value as $keyLine => $line) {
                $result[$keyLine][$key] = trim($line);
            }
        }

        return $result;
    }

    /**
     * Remove control characters
     *
     * @author nerdalertdk (https://github.com/nerdalertdk)
     * @link https://github.com/Nizarii/arma-rcon-class-php/issues/4 The related GitHub issue
     *
     * @param $str string
     *
     * @return string
     */
    private function cleanList($str)
    {
        return preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $str);
    }
}
