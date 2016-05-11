<?php

/**
 * Only include this file if you are *NOT* using Composer.
 * 
 * If you use Composer, install ARC with `composer require nizarii/arma-rcon-class`.
 */


// Include all exceptions
require_once __DIR__ . 'Exceptions/ARCException.php';
require_once __DIR__ . 'Exceptions/AuthorizationException.php';
require_once __DIR__ . 'Exceptions/PacketException.php';
require_once __DIR__ . 'Exceptions/SocketException.php';


// Include all necessary classes
require_once __DIR__ . 'Core/Connection.php';
require_once __DIR__ . 'Core/Sender.php';
require_once __DIR__ . 'Core/Operator.php';
require_once __DIR__ . 'ARC.php';