<?php

use Memcrab\Queue\RabbitMQ;
use Memcrab\Queue\RMQPool;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

RMQPool::declareConnection(
    environment: 'dev',
    host: '127.0.0.1',
    port: '5672',
    username: 'dev',
    password: 'dev',
    ErrorHandler: new Logger('log'),
    waitTimeoutPool: 3,
    capacity: 1,
    heartbeat: 60
);

# Connections
Co::run(function () {
    # Start init and ping connections
    RMQPool::obj()->setConnections();

    # Get connection from Pool
    /** @var RabbitMQ $Queue */
    $Queue = RMQPool::obj()->get();

    $queueName = "test";
    $Queue->sendMessage(
        messageBody: ['data' => 'test message'],
        routingKey: $queueName
    );

    # Return connection to Pool
    RMQPool::obj()->put($Queue);
});

# Register queue, exchanger and receive messages
Co::run(function () {
    $queueName = "test";

    /** @var RabbitMQ $Queue */
    $Queue = RMQPool::obj()->get();

    $Queue->registerQueue($queueName, false, true, false, false);
    $Queue->registerExchange($Queue->getLetterExchange(), 'direct', false, true, false);
    $Queue->queueBind($queueName, $Queue->getLetterExchange(), $Queue->getLetterRoutineKey($queueName));

    $Queue->receiveMessage($queueName, '', false, false, false, false, function ($msg) {
        $message = $msg->body;
        $params = json_decode($message, true);
        # message handling
    });
});