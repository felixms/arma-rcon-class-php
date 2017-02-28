<?php

/**
 * ARC is a lightweight class, helping you to send commands to your ARMA server via RCon.
 *
 * @author    Felix Schäfer
 * @copyright 2017 Felix Schäfer
 * @license   MIT-License
 * @link      https://github.com/Nizarii/arma-rcon-php-class Github repository of ARC
 * @version   2.1.5
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
        'sendHeartbeat' => false,
        'timeoutSec'    => 1,
        'autosavebans'  => false,
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
        
        if ($this->options['sendHeartbeat']) {
            $this->sendHeartbeat();
        }
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
        if (array_key_exists('heartbeat', $this->options)) {
            @trigger_error("The 'heartbeat' option is deprecated since version 2.1.2 and will be removed in 3.0. Use 'sendHeartbeat' instead.", E_USER_DEPRECATED);
            $this->options['sendHeartbeat'] = $this->options['heartbeat'];
        }
    }

    /**
     * Checks for type issues in the option array
     */
    private function checkOptionTypes()
    {
        if (!is_int($this->options['timeoutSec'])) {
            throw new \Exception(
                sprintf("Expected option 'timeoutSec' to be integer, got %s", gettype($this->options['timeoutSec']))
            );
        } elseif (!is_bool($this->options['sendHeartbeat'])) {
            throw new \Exception(
                sprintf("Expected option 'sendHeartbeat' to be boolean, got %s", gettype($this->options['sendHeartbeat']))
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
     * Sends a heartbeat packet to the server
     *
     * @throws \Exception if sending the command fails
     */
    private function sendHeartbeat()
    {
        $hbMsg = 'BE'.chr(hexdec('7d')).chr(hexdec('8f')).chr(hexdec('ef')).chr(hexdec('73'));
        $hbMsg .= chr(hexdec('ff')).chr(hexdec('02')).chr(hexdec('00'));

        if ($this->writeToSocket($hbMsg) === false) {
            throw new \Exception('Failed to send heartbeat packet!');
        }
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
        
        if ($this->options['autosavebans']) {
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
        
        if ($this->options['autosavebans']) {
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
       
        if ($this->options['autosavebans']) {
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
