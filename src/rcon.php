<?php

/**
 *
 * An easy-to-use PHP class to send commands via  RCon to Arma servers.
 *
 * @author  Felix Schäfer <nizari@starwolf-dev.com>
 * @since   September 26, 2015
 * @link    https://github.com/Nizarii/arma3-rcon-php-class
 * @license MIT-License
 * @version 1.0.0
 *
 */

class ARC {

    /**
     * ARC Options
     *
     * @var array
     */
    private $options = array (
        'send_heartbeat'       => true,
    );


    /**
     * Server IP
     *  The server IP of the BattlEye server where the commands are going send to.
     *
     * @var string
     */
    private $serverIP;


    /**
     * Server port
     *  The specific port of the BattlEye server.
     *
     * @var int
     */
    private $serverPort;


    /**
     * RCon password
     *  The required password for logging in.
     *
     * @var string
     */
    private $RCONpassword;


    /**
     * Socket
     *  The required socket for sending commands
     *
     * @var object
     */
    private $socket;


    /**
     * msgseq
     *  Get's bigger each command
     *
     * @var integer
     */
    private $msgseq;


    /**
     * Class constructor
     *
     * @param string $serverIP        IP of the Arma server
     * @param integer $serverPort     Port of the Arma server
     * @param string  $RCONpassword   The RCon password required by BattlEye
     * @param array  $options         Options of ARC
     *
     * @throws Exception if the socket creation fails
     */
    public function __construct($serverIP, $serverPort = 2302, $RCONpassword ,array $options = array())
    {
        $this->serverIP = $serverIP;
        $this->serverPort = $serverPort;

        $this->RCONpassword = $RCONpassword;
        $this->options = array_merge($this->options, $options);

        $this->msgseq = 0;

        $this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

        if(!$this->socket)
        {
            throw new Exception('[ARC] Failed creating a socket!');
        }

        $this->send_login();

    }


    /**
     * Class destructor
     */
    public function __destruct()
    {
        $this->connection_close();
    }

    /**
     * Closes the connection
     */
    private function connection_close()
    {
        $this->send("Exit");
        socket_close($this->socket);
    }


    /**
     * Sends the login data to the server in order to send commands later
     *
     * @throws Exception if login fails
     */
    private function send_login()
    {
        $loginmsg = $this->get_loginmessage();

        $len = strlen($loginmsg);

        $sent = socket_sendto($this->socket, $loginmsg, $len, 0, $this->serverIP, $this->serverPort);

        if($sent == false)
        {
            throw new Exception('[ARC] Failed to send login!');
        }

        socket_recvfrom($this->socket, $buf, 64, 0, $this->serverIP, $this->serverPort);

        if(ord($buf[strlen($buf)-1]) == 0)
        {
            throw new Exception('[ARC] Login failed!');
        }

        $recv = socket_recvfrom($this->socket, $buf, 64, 0, $this->serverIP, $this->serverPort);

        if($recv == false)
        {
            throw new Exception('[ARC] Failed to receive data: '.socket_last_error());
        }

        if ($this->options['send_heartbeat'])
        {
            $this->send_heartbeat();
        }

    }


    /**
     * Generates the password's CRC32 data
     *
     * @return string
     */
    private function get_authCRC()
    {
        $authCRC = crc32(chr(255).chr(00).trim($this->RCONpassword));
        $authCRC = sprintf("%x", $authCRC);
        $authCRC = array(substr($authCRC,-2,2),substr($authCRC,-4,2),substr($authCRC,-6,2),substr($authCRC,0,2));

        return $authCRC;
    }



    /**
     * Generates the message's CRC32 data
     *
     * @return string
     */
    private function get_msgCRC($command)
    {
        $msgCRC = crc32(chr(255).chr(01).chr(hexdec(sprintf('%01b',$this->msgseq))).$command);
        $msgCRC = sprintf("%x", $msgCRC);
        $msgCRC = array(substr($msgCRC,-2,2),substr($msgCRC,-4,2),substr($msgCRC,-6,2),substr($msgCRC,0,2));

        return $msgCRC;
    }


    /**
     * Generates the login message
     *
     * @return string
     */
    private function get_loginmessage()
    {
        $authCRC = $this->get_authCRC();

        $loginmsg = "BE".chr(hexdec($authCRC[0])).chr(hexdec($authCRC[1])).chr(hexdec($authCRC[2])).chr(hexdec($authCRC[3]));
        $loginmsg .= chr(hexdec('ff')).chr(hexdec('00')).$this->RCONpassword;

        return $loginmsg;
    }


    /**
     * Sends optional a heartbeat to the server
     *
     * @throws Exception if sending the command fails
     */
    private function send_heartbeat()
    {
        $hb_msg = "BE".chr(hexdec("7d")).chr(hexdec("8f")).chr(hexdec("ef")).chr(hexdec("73"));
        $hb_msg .= chr(hexdec('ff')).chr(hexdec('02')).chr(hexdec('00'));
        $len = strlen($hb_msg);

        $sent_hb = socket_sendto($this->socket, $hb_msg, $len, 0, $this->serverIP, $this->serverPort);

        if ($sent_hb == false) {
            throw new Exception('[ARC] Failed to send heartbeat to server: '.socket_last_error());
        }
    }


    /**
     * The heart of this class - this function sends the RCON command
     *
     * @param string $command The command sent to the server
     * @throws Exception if sending the command fails
     */
    private function send($command)
    {
        $msgCRC = $this->get_msgCRC($command);

        $buf = "BE".chr(hexdec($msgCRC[0])).chr(hexdec($msgCRC[1])).chr(hexdec($msgCRC[2])).chr(hexdec($msgCRC[3]));
        $buf .= chr(hexdec('ff')).chr(hexdec('01')).chr(hexdec(sprintf('%01b',$this->msgseq))).$command;

        $len = strlen($buf);

        $sent_command = socket_sendto($this->socket, $buf, $len, 0, $this->serverIP, $this->serverPort);

        $this->msgseq++;

        if($sent_command == false)
        {
            throw new Exception('[ARC] Failed to send command: '.socket_last_error());
        }


    }


    /**
     * Sends a custom command to the BattlEye server
     *
     * @param string $command The command sent to the server
     */
    public function command($command)
    {
        $this->send($command);
    }


    /**
     * Kicks a player who is currently on the server
     *
     * @param string $player The player who should be kicked
     */
    public function kick_player($player)
    {
        $this->send("kick ".$player);
    }


    /**
     * Sends a global message to all players
     *
     * @param string $message The message to send
     */
    public function say_global($message)
    {
        $this->send("Say -1 ".$message);
    }


    /**
     * Sends a global message to all players
     *
     * @param string $player Player who is sent the message
     * @param string $message The message for the player
     */
    public function say_player($player, $message)
    {
        $this->send("Say ".$player." ".$message);
    }


    /**
     * Loads the "scripts.txt" file without the need to restart the server
     */
    public function load_scripts()
    {
        $this->send("loadScripts");
    }


    /**
     * Changes the MaxPing value. If a player has a higher ping, he will be kicked from the server
     *
     * @param integer $ping Max ping
     */
    public function max_ping($ping)
    {
        $this->send("MaxPing ".$ping);
    }


    /**
     * Changes the RCon password
     *
     * @param string $password New password
     */
    public function change_password($password)
    {
        $this->send("RConPassword ".$password);
    }


    /**
     * (Re)load the BE ban list from bans.txt.
     */
    public function load_bans()
    {
        $this->send("loadBans");
    }


    /**
     * Ban a player's BE GUID from the server. If time is not specified or 0, the ban will be permanent; if reason is not specified the player will be kicked with "Banned"
     *
     * @param string $player Player who will be banned
     * @param string $reason Reason why the player is banned
     * @param integer $time How long the player is banned (0 = permanent)
     */
    public function ban_player($player, $reason = "Banned", $time = 0)
    {
        $this->send("ban ".$player." ".$time." ".$reason);
    }


    /**
     * Same as "ban_player", but allows to ban a player that is not currently on the server.
     *
     * @param string $player Player who will be banned
     * @param string $reason Reason why the player is banned
     * @param integer $time How long the player is banned (0 = permanent)
     */
    public function add_ban($player, $reason = "Banned", $time = 0)
    {
        $this->send("addBan ".$player." ".$time." ".$reason);
    }


    /**
     * Removes a ban
     *
     * @param integer $banid Ban who will be removed
     */
    public function remove_ban($banid)
    {
        $this->send("removeBan ".$banid);
    }


    /**
     * Removes expired bans from bans file
     */
    public function write_bans()
    {
        $this->send("writeBans");
    }

}
