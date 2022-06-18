<?php

declare(strict_types=1);

namespace Memcrab\Queue;

use PhpAmqpLib\Connection\AMQPStreamConnection as AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage as AMQPMessage;

class RMQ implements Queue
{
    private static RMQ $instance;
    private static $conectionProperties;
    private $client;
    private $channel;

    public function __construct()
    {
    }
    public function __clone()
    {
    }
    public function __wakeup()
    {
    }

    /**
     * @return Queue
     */
    public static function obj(): Queue
    {
        if (!isset(self::$instance) || !(self::$instance instanceof self)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @param array $properties
     * 
     * @return array
     */
    public static function setConnectionProperties(array $properties): void
    {
        self::$conectionProperties = [
            'host' => $properties[0],
            'port' => $properties[1],
            'username' => $properties[2],
            'pass' => $properties[3]
        ];
    }

    /**
     * @return Queue
     */
    public function connect(): Queue
    {
        $client = $this->client = new AMQPStreamConnection(
            self::$conectionProperties['host'],
            self::$conectionProperties['port'],
            self::$conectionProperties['username'],
            self::$conectionProperties['pass']
        );
        $this->channel = $client->channel();

        return $this;
    }

    /**
     * @param string $name
     * @param bool $passive
     * @param bool $durable
     * @param bool $exclusive
     * @param bool $auto_delete
     * @return array
     */
    public function registerQueue(
        string $name,
        bool $passive = false,
        bool $durable = false,
        bool $exclusive = false,
        bool $auto_delete = false
    ): array {
        $result = $this->channel->queue_declare($name, $passive, $durable, $exclusive, $auto_delete);

        return $result;
    }

    /**
     * @param string $name
     * @param string $type
     * @param bool $passive
     * @param bool $durable
     * @param bool $auto_delete
     * 
     * @return [type]
     */
    public function registerExchange(
        string $name,
        string $type,
        bool $passive = false,
        bool $durable = false,
        bool $auto_delete = false
    ) {
        $result = $this->channel->exchange_declare($name, $type, $passive, $durable, $auto_delete);

        return $result;
    }

    /**
     * @param string $name
     * @param array $messageBody
     * @param string $exchange
     * 
     * @return [type]
     */
    public function sendMessage(string $name, array $messageBody, string $exchange = '')
    {
        $msg = new AMQPMessage(serialize($messageBody));
        $result = $this->channel->basic_publish($msg, $exchange, $name);

        return $result;
    }

    /**
     * @param string $name
     * @param string $consumer_tag
     * @param bool $no_local
     * @param bool $no_ack
     * @param bool $exclusive
     * @param bool $nowait
     * @param callable $callback
     * @return mixed
     */
    public function receiveMessage(
        string $name,
        string $consumer_tag = '',
        bool $no_local = false,
        bool $no_ack = false,
        bool $exclusive = false,
        bool $nowait = false,
        $callback = null
    ) {
        $result = $this->channel->basic_consume($name, $consumer_tag, $no_local, $no_ack, $exclusive, $nowait, $callback);
        while ($this->channel->is_open()) {
            $this->channel->wait();
        }

        return $result;
    }

    /**
     * @param string $name
     * @param string $exchange
     * @param string $routing_key
     * @return mixed
     */
    public function queueBind(
        string $name,
        string $exchange,
        string $routing_key
    ) {
        $result = $this->channel->queue_bind($name, $exchange, $routing_key);

        return $result;
    }

    /**
     * @return object
     */
    public function client(): object
    {
        return $this->client;
    }

    /**
     * @return void
     */
    public static function shutdown(): void
    {
        self::$instance->channel->close();
        self::$instance->client->close();
        unset(self::$instance->channel);
        unset(self::$instance->client);
    }
}
