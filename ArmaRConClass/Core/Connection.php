<?php

namespace Nizarii\ArmaRConClass\Core;
use Nizarii\ArmaRConClass\Exceptions\AuthorizationException;
use Nizarii\ArmaRConClass\Exceptions\PacketException;
use Nizarii\ArmaRConClass\Exceptions\SocketException;


/**
 * Class Connection
 *
 * Sorry, I am busy atm, description will follow ;)
 * @internal
 */
class Connection {



    /**
     * ARC Options
     *
     * @var array
     */
    public $options = [
        'timeout_seconds'  => 1,
        'throw_exceptions' => true
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
     */
    public $socket = null;


    /**
     * RCon password
     *  The required password for logging in.
     *
     * @var string
     */
    private $password;

    
    
    public function __construct(array $options) {
        $this->options = array_merge($this->options, $options);
    }


    public function create($ServerIP, $ServerPort, $Password) {
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