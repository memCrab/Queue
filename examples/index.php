<?php 

require_once __DIR__ . "/../vendor/autoload.php";

use Memcrab\Queue;
use Memcrab\Queue\SQS;
use Memcrab\Queue\RMQ;

// $sqsConfig = [
//     $region,
//     $key,
//     $secret,
//     $version, 
//     $endpoint
// ];

$rmqConfig = [
    'localhost',
    '5672',
    'guest',
    'guest'
];

$connection = RMQ::obj();
$connection->setConnectionProperties(['host' => 'localhost', 'port' => 5672, 'username' => 'guest', 'password' => 'guest']);
$connection->connect();
$connection->registerQueue('ffff2222', false, true, false, false);
$connection->sendMessage('ffff2222', ['test']);
$connection->receiveMessage('ffff2222');
$connection->connectionStatus();

