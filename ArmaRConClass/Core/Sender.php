<?php

namespace Nizarii\ArmaRConClass\Core;


/**
 * Class Sender
 *
 * @internal
 */
class Sender {
    
    
    private $connection;
    
    
    private $head;
    
    
    private $disconnected = false;
    


    public function __construct(Connection $connection) {
        $this->connection = $connection;
    }
    

    /**
     * Closes the socket/connection. If you want to reconnect,
     * don't forget to call connect(), in order to create a new socket
     *
     * @see connect()
     */
    public function disconnect() {
        if ( $this->disconnected )
            return;
        
        $this->send('Exit');
        fclose($this->connection->socket);

        $this->connection = null;
        $this->disconnected = true;
    }


    protected function send($command) {
        if ( $this->disconnected )
            return false;
        
        $msgCRC = $this->getMsgCRC($command);
        $head = "BE".chr(hexdec($msgCRC[0])).chr(hexdec($msgCRC[1])).chr(hexdec($msgCRC[2])).chr(hexdec($msgCRC[3])).chr(hexdec('ff')).chr(hexdec('01')).chr(hexdec(sprintf('%01b', 0)));

        $msg = $head.$command;
        $this->head = $head;

        return fwrite($this->connection->socket, $msg) === false ? false : true;
    }


    /**
     * Generates the message's CRC32 data
     *
     * @param string $command   The message which will be prepared for being sent to the server
     * @return string           Message which can be sent to the server
     */
    private function getMsgCRC($command) {
        $msgCRC = sprintf("%x", crc32(chr(255).chr(01).chr(hexdec(sprintf('%01b', 0))).$command));
        $msgCRC = [substr($msgCRC,-2,2),substr($msgCRC,-4,2),substr($msgCRC,-6,2),substr($msgCRC,0,2)];

        return $msgCRC;
    }


    /**
     * Receives the answer form the server
     *
     * @return string           Any answer from the server, except the log-in message
     */
    protected function getAnswer() {
        $get = function() {
            return substr(fread($this->connection->socket, 102400), strlen($this->head));
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
}