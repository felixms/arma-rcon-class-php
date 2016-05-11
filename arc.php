<?php

/**
 *
 * ARC, an easy-to-use PHP class to send commands via  RCon to Arma servers.
 *
 * @author   Felix Schäfer <nizari@starwolf-dev.com>
 * @since    September 26, 2015
 * @link     https://github.com/Nizarii/arma-rcon-php-class
 * @license  MIT-License
 * @version  2.0
 *
 */


namespace Nizarii;


class ARC {


    /**
     * ARC Options
     *
     * @var array
     */
    public $options = array (
        'heartbeat'       => false,
        'timeout_sec'      => 1,
    );


    /**
     * Server IP
     *  The server IP of the BattlEye server where the commands are going send to.
     *
     * @var string
     */
    public $serverIP;


    /**
     * Server port
     *  The specific port of the BattlEye server.
     *
     * @var int
     */
    public $serverPort;


    /**
     * RCon password
     *  The required password for logging in.
     *
     * @var string
     */
    public $RCONpassword;


    /**
     * Socket
     *  The required socket for sending commands
     *
     * @var resource
     */
    private $socket = null;


    /**
     * Connection Status
     *  If this value is true, it means that the connection is closed,
     *  so connect() is available
     *
     * @var bool
     */
    private $disconnected = true;


    /**
     * Head
     *  The head of the message, which was sent to the server
     *
     * @var string
     */
    private $head;


    /**
     * Class constructor
     *
     * @param string $serverIP             IP of the Arma server
     * @param integer $serverPort          Port of the Arma server
     * @param string  $RCONpassword        RCon password required by BattlEye
     * @param array  $options              Options array of ARC
     */
    public function __construct($serverIP, $serverPort = 2302, $RCONpassword , array $options = array())
    {
        $this->serverIP = $serverIP;
        $this->serverPort = $serverPort;
        $this->RCONpassword = $RCONpassword;
        $this->options = array_merge($this->options, $options);

        $this->connect();
    }


    /**
     * Class destructor
     */
    public function __destruct()
    {
        $this->close();
    }


    /**
     * Sends the login data to the server in order to send commands later
     *
     * @throws \Exception      If login fails (password wrong)
     */
    private function authorize()
    {
        if ( fwrite($this->socket, $this->getLoginMessage()) === false )
            throw new \Exception('[ARC] Failed to send login!');

        $result = fread($this->socket, 16);

        if ( ord($result[strlen($result)-1]) == 0 )
            throw new \Exception('[ARC] Login failed, wrong password!');
    }


    /**
     * Generates the password's CRC32 data
     *
     * @return string
     */
    private function getAuthCRC()
    {
        $authCRC = sprintf("%x", crc32(chr(255).chr(00).trim($this->RCONpassword)));
        $authCRC = array(substr($authCRC,-2,2),substr($authCRC,-4,2),substr($authCRC,-6,2),substr($authCRC,0,2));

        return $authCRC;
    }


    /**
     * Generates the message's CRC32 data
     *
     * @param string $command   The message which will be prepared for being sent to the server
     * @return string           Message which can be sent to the server
     */
    private function getMsgCRC($command)
    {
        $msgCRC = sprintf("%x", crc32(chr(255).chr(01).chr(hexdec(sprintf('%01b', 0))).$command));
        $msgCRC = array(substr($msgCRC,-2,2),substr($msgCRC,-4,2),substr($msgCRC,-6,2),substr($msgCRC,0,2));

        return $msgCRC;
    }


    /**
     * Generates the login message
     *
     * @return string           The message for logging in, containing the RCon password
     */
    private function getLoginMessage()
    {
        $authCRC = $this->getAuthCRC();

        $loginmsg = "BE".chr(hexdec($authCRC[0])).chr(hexdec($authCRC[1])).chr(hexdec($authCRC[2])).chr(hexdec($authCRC[3]));
        $loginmsg .= chr(hexdec('ff')).chr(hexdec('00')).$this->RCONpassword;

        return $loginmsg;
    }


    /**
     * Sends optional a heartbeat to the server
     *
     * @throws \Exception  If sending the command fails
     */
    private function sendHeartbeat()
    {
        $hb_msg = "BE".chr(hexdec("7d")).chr(hexdec("8f")).chr(hexdec("ef")).chr(hexdec("73"));
        $hb_msg .= chr(hexdec('ff')).chr(hexdec('02')).chr(hexdec('00'));

        if ( fwrite($this->socket, $hb_msg) === false )
            throw new \Exception('[ARC] Failed to send heartbeat packet!');
    }


    /**
     * Receives the answer form the server
     *
     * @return string           Any answer from the server, except the log-in message
     */
    private function getAnswer()
    {
        $get = function() {
            return substr(fread($this->socket, 102400), strlen($this->head));
        };

        $output = '';

        do {
            $answer = $get();

            while ( strpos($answer,'RCon admin') !== false )
                $answer = $get();

            $output .= $answer;
        } while ( $answer != '' );

        return $output;
    }


    /**
     * The heart of this class - this function actually sends the RCON command
     *
     * @param $command string   The command sent to the server
     * @return bool             Whether sending the command was successful or not
     * @throws \Exception       If the connection is closed
     */
    private function send($command)
    {
        if ( $this->disconnected )
            throw new \Exception('[ARC] Failed to send command, because the connection is closed!');

        $msgCRC = $this->getMsgCRC($command);
        $head = "BE".chr(hexdec($msgCRC[0])).chr(hexdec($msgCRC[1])).chr(hexdec($msgCRC[2])).chr(hexdec($msgCRC[3])).chr(hexdec('ff')).chr(hexdec('01')).chr(hexdec(sprintf('%01b', 0)));
        $msg = $head.$command;
        $this->head = $head;

        return fwrite($this->socket, $msg) === false ? false : true;
    }


    /**
     * Closes the socket/connection
     */
    public function close()
    {
        if ( $this->disconnected )
            return;

        $this->send("Exit");

        fclose($this->socket);

        $this->socket = null;
        $this->disconnected = true;
    }


    /**
     * Creates again a connection to the server, manually closing the
     * connection before is not needed. You may change the server-related data by using the optional parameters
     *
     * @throws \Exception       If the password is wrong
     * @throws \Exception       If sending the heartbeat packet fails
     * @throws \Exception       If creating the socket fails
     */
    private function connect()
    {
        if ( !$this->disconnected) 
            $this->close();

        $this->socket = @fsockopen("udp://".$this->serverIP, $this->serverPort, $errno, $errstr, $this->options['timeout_sec']);

        stream_set_timeout($this->socket, $this->options['timeout_sec']);
        stream_set_blocking($this->socket, true);

        if ( !$this->socket )
            throw new \Exception('[ARC] Failed to create socket!');

        $this->authorize();

        if ( $this->options['heartbeat'] )
            $this->sendHeartbeat();

        $this->disconnected = false;
    }


    /**
     * Sends a custom command to the BattlEye server
     *
     * @param string $command   Command sent to the server
     * @return string           Answer from the server
     */
    public function command($command)
    {
        return $this->send($command) ? $this->getAnswer() : false;
    }


    /**
     * Kicks a player who is currently on the server
     *
     * @param string $reason    Message displayed why the player is kicked
     * @param integer $player   The player who should be kicked
     * @return bool             Whether sending the command was successful or not
     * @throws \Exception
     */
    public function kickPlayer($player, $reason = 'Admin Kick')
    {
        return $this->send("kick $player $reason");
    }


    /**
     * Sends a global message to all players
     *
     * @param string $message   The message to send
     * @return bool             Whether sending the command was successful or not
     */
    public function sayGlobal($message)
    {
        return $this->send("Say -1 ".$message);
    }


    /**
     * Sends a message to a specific player
     *
     * @param integer $player   Player who is sent the message
     * @param string $message   The message for the player
     * @return bool             Whether sending the command was successful or not
     */
    public function sayPlayer($player, $message)
    {
        return $this->send("Say $player $message");
    }


    /**
     * Loads the "scripts.txt" file without the need to restart the server
     *
     * @return bool             Whether sending the command was successful or not
     */
    public function loadScripts()
    {
        return $this->send("loadScripts");
    }


    /**
     * Changes the MaxPing value. If a player has a higher ping, he will be kicked from the server
     *
     * @param integer $ping     Max ping
     * @return bool             Whether sending the command was successful or not
     */
    public function maxPing($ping)
    {
        return $this->send("MaxPing $ping");
    }


    /**
     * Changes the RCon password
     *
     * @param string $password  The new password
     * @return bool             Whether sending the command was successful or not
     */
    public function changePassword($password)
    {
        return $this->send("RConPassword $password");
    }


    /**
     * (Re)load the BE ban list from bans.txt
     *
     * @return bool             Whether sending the command was successful or not
     */
    public function loadBans()
    {
        return $this->send("loadBans");
    }


    /**
     * Gets a list of all players currently on the server
     *
     * @return string|bool      The list of all players on the server or, if sending failed, false
     */
    public function getPlayers()
    {
        return $this->send("players") ? $this->getAnswer() : false;
    }


    /**
     * Gets a list of all players currently on the server as an array
     *
     * @author nerdalertdk (https://github.com/nerdalertdk)
     * @link https://github.com/Nizarii/arma-rcon-class-php/issues/4
     * @return array|bool      The list of all players on the server or, if sending failed, false
     */
    public function getPlayersArray() {
        $playersRaw = $this->send("players");

        if ( $playersRaw === false )
            return false;

        $players = $this->cleanList($playersRaw);
        preg_match_all("#(\d+)\s+(\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d+\b)\s+(\d+)\s+([0-9a-fA-F]+)\(\w+\)\s([\S ]+)$#im", $players, $str);
        $result = $this->formatList($str);

        return $result;
    }


    /**
     * Gets a list of all bans
     *
     * @return string|bool      The list of missions or, if sending failed, false
     */
    public function getMissions()
    {
        return $this->send("missions") ? $this->getAnswer() : false;
    }


    /**
     * Ban a player's BE GUID from the server. If time is not specified or 0, the ban will be permanent;.
     * If reason is not specified the player will be kicked with the message "Banned".
     *
     * @param string $player    Player who will be banned
     * @param string $reason    Reason why the player is banned
     * @param integer $time     How long the player is banned (0 = permanent)
     * @return bool             Whether sending the command was successful or not
     */
    public function banPlayer($player, $reason = "Banned", $time = 0)
    {
        return $this->send("ban $player $time $reason");
    }


    /**
     * Same as "ban_player", but allows to ban a player that is not currently on the server
     *
     * @param string $player    Player who will be banned
     * @param string $reason    Reason why the player is banned
     * @param integer $time     How long the player is banned (0 = permanent)
     * @return bool             Whether sending the command was successful or not
     */
    public function addBan($player, $reason = "Banned", $time = 0)
    {
        return $this->send("addBan $player $time $reason");
    }


    /**
     * Removes a ban
     *
     * @param integer $banid    Ban who will be removed
     * @return bool             Whether sending the command was successful or not
     */
    public function removeBan($banid)
    {
        return $this->send("removeBan $banid");
    }


    /**
     * Gets aan array of all bans
     *
     * @author nerdalertdk (https://github.com/nerdalertdk)
     * @link https://github.com/Nizarii/arma-rcon-class-php/issues/4
     * @return array|bool      The list of bans or, if sending failed, false
     */
    public function getBansArray() {
        $bansRaw = $this->send("bans");

        if ( $bansRaw === false )
            return false;

        $bans = $this->cleanList($bansRaw);
        preg_match_all("#(\d+)\s+(\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d+\b)\s+(\d+)\s+([0-9a-fA-F]+)\(\w+\)\s([\S ]+)$#im", $bans, $str);
        $result = $this->formatList($str);

        return $result;
    }


    /**
     * Gets a list of all bans
     *
     * @return string|bool      The list of bans or, if sending failed, false
     */
    public function getBans()
    {
        return $this->send("bans") ? $this->getAnswer() : false;
    }


    /**
     * Removes expired bans from bans file
     *
     * @return bool             Whether sending the command was successful or not
     */
    public function writeBans()
    {
        return $this->send("writeBans");
    }


    /**
     * Converts BE text "array" list to Array
     *
     * @author nerdalertdk (https://github.com/nerdalertdk)
     * @link https://github.com/Nizarii/arma-rcon-class-php/issues/4
     * @return array
     */
    private function formatList($str)
    {
        // Remove first array
        array_shift($str);
        
        // Create return array
        $result = array();
        
        // Loop true the main arrays, each holding a value
        foreach($str as $key => $value)
        {
            // Combine's each main vaule in to new array
            foreach($value as $keyLine => $line)
            {
                $result[$keyLine][$key] = trim($line);
            }
        }
        
        return $result;
    }


    /**
     * Remove control characters
     *
     * @author nerdalertdk (https://github.com/nerdalertdk)
     * @link https://github.com/Nizarii/arma-rcon-class-php/issues/4
     * @return string
     */
    private function cleanList($str)
    {
        return preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $str );
    }
}


