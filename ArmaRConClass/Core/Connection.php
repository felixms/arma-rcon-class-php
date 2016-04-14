<?php

namespace Nizarii\ArmaRConClass\Core;
use Nizarii\ArmaRConClass\Exceptions\AuthorizationException;
use Nizarii\ArmaRConClass\Exceptions\PacketException;
use Nizarii\ArmaRConClass\Exceptions\SocketException;


/**
 * Class Connection
 *
 * Sorry, I am busy atm, description will follow ;)
 */
class Connection {



    /**
     * ARC Options
     *
     * @var array
     */
    protected $options = [
        'timeout_sec'      => 1
    ];


    /**
     * @var bool
     */
    private $disconnected = true;

    
    /**
     * Socket
     *  The required socket for sending commands
     *
     * @var resource
     * @internal
     */
    public $socket = null;


    /**
     * RCon password
     *  The required password for logging in.
     *
     * @var string
     */
    private $password;


    
    /**
     * @param $ServerIP
     * @param $ServerPort
     * @param $Password
     *
     * @return Operator
     * @throws AuthorizationException
     * @throws PacketException
     * @throws SocketException
     */
    public function connnect($ServerIP, $ServerPort, $Password) {
        if ( !$this->disconnected)
            $this->disconnect();

        $this->password = $Password;

        $this->socket = @fsockopen('udp://' . $ServerIP, $ServerPort, $errno, $errstr, $this->options['timeout_sec']);

        stream_set_timeout($this->socket, $this->options['timeout_sec']);
        stream_set_blocking($this->socket, true);

        if ( !$this->socket ) 
            throw new SocketException('[ARC] Failed to create socket!');

        $this->authorize();

        $this->disconnected = false;
        
        return new Operator($this);
    }


    /**
     * Closes the socket/connection. If you want to reconnect,
     * don't forget to call connect(), in order to create a new socket
     *
     * @see connect()
     */
    public function disconnect() {
        if ( $this->disconnected ) return;

        fclose($this->socket);

        $this->connection = null;
        $this->disconnected = true;
    }
    

    /**
     * Sends the login data to the server in order to send commands later
     *
     * @throws AuthorizationException      If login fails (password wrong)
     */
    private function authorize() {
        if ( fwrite($this->socket, $this->getLoginMessage()) === false )
            throw new PacketException('[ARC] Failed to send login!');

        $result = fread($this->socket, 16);

        if ( ord($result[strlen($result)-1]) == 0 )
            throw new AuthorizationException('[ARC] Login failed, wrong password!');
    }

    
    /**
     * Generates the password's CRC32 data
     *
     * @return string
     */
    private function getAuthCRC() {
        $authCRC = sprintf("%x", crc32(chr(255).chr(00).trim($this->password)));
        $authCRC = array(substr($authCRC,-2,2),substr($authCRC,-4,2),substr($authCRC,-6,2),substr($authCRC,0,2));

        return $authCRC;
    }
    

    /**
     * Generates the login message
     *
     * @return string           The message for logging in, containing the RCon password
     */
    private function getLoginMessage() {
        $authCRC = $this->getAuthCRC();

        $loginmsg = "BE".chr(hexdec($authCRC[0])).chr(hexdec($authCRC[1])).chr(hexdec($authCRC[2])).chr(hexdec($authCRC[3]));
        $loginmsg .= chr(hexdec('ff')).chr(hexdec('00')).$this->password;

        return $loginmsg;
    }
}