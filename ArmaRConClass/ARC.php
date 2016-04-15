<?php

namespace Nizarii\ArmaRConClass;

use Nizarii\ArmaRConClass\Core\Connection;


/**
 * Class ARC
 *
 * ARC allows you connecting and sending commands easily via RCon to your ARMA game server
 *
 * @author   Felix Schäfer <nizari@starwolf-dev.com>
 * @link     https://github.com/Nizarii/arma-rcon-php-class
 * @license  MIT-License
 * @version  2.0
 */
class ARC {


    private $options = array();
    

    public function __construct(array $options = array()) {
        $this->options = array_merge($this->options, $options);
    }


    public function connect($ServerIP, $ServerPort, $Password) {
        $connection = new Connection($this->options);
        return $connection->create($ServerIP, $ServerPort, $Password);
    }
}