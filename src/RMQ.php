<?php

declare(strict_types=1);

namespace Memcrab\Queue;

use PhpAmqpLib\Connection\AMQPStreamConnection as AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage as AMQPMessage;

class RMQ implements Queue
{
    private static RMQ $instance;
    private static string $host;
    private static int $port;
    private static string $username;
    private static string $password;
    private AMQPStreamConnection $client;
    private $channel;


    private function __construct()
    {
    }
    private function __clone()
    {
    }
    public function __wakeup()
    {
    }

    /**
     * @return Queue
     */
    public static function obj(): self
    {
        if (!isset(self::$instance) || !(self::$instance instanceof self)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @param array $properties
     * 
     * @return void
     */
    public static function setConnectionProperties(array $properties): void
    {
        try {
            if (!isset($properties['host']) || empty($properties['host']) || !is_string($properties['host'])) {
                throw new \Exception("RabbitMQ `host` property need to be string");
            }
            if (!isset($properties['port']) || !is_int($properties['port'])) {
                throw new \Exception("RabbitMQ `port` property need to be int");
            }
            if (!isset($properties['username']) || empty($properties['username']) || !is_string($properties['username'])) {
                throw new \Exception("RabbitMQ `username` property need to be string");
            }
            if (!isset($properties['password']) || empty($properties['password']) || !is_string($properties['password'])) {
                throw new \Exception("RabbitMQ `password` property need to be string");
            }

            self::$host = $properties['host'];
            self::$port = $properties['port'];
            self::$username = $properties['username'];
            self::$password = $properties['password'];
        } catch (\Exception $e) {
            error_log((string) $e);
            throw $e;
        }
    }

    /**
     * @return Queue
     */
    public function connect(): self
    {
        $this->client = new AMQPStreamConnection(
            self::$host,
            self::$port,
            self::$username,
            self::$password
        );

        $this->channel = $this->client->channel();

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
    public function registerQueue(string $name, bool $passive = false, bool $durable = false, bool $exclusive = false, bool $auto_delete = false): array
    {
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
    public function registerExchange(string $name, string $type, bool $passive = false, bool $durable = false, bool $auto_delete = false)
    {
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

    // TODO: add destructor with disconnect of connection
    // TODO: recheck if we realy need do both channel and client close() in shutdown or only client? does any of them has method close? need to look to documentaion of Rabbit SDK
    // TODO: add checks in shutdown if instance are already initialized before call close() methods as it static method and can be called it self even before any obj() initialization
    /**
     * @return void
     */
    public static function shutdown(): void
    {
        if (isset(self::$instance->channel)) {
            self::$instance->channel->close();
        }
        if (isset(self::$instance->client)) {
            self::$instance->client->close();
        }
    }

    public function __destruct()
    {
        if (!empty($this->channel)) {
            unset($this->channel);
        }
        if (!empty($this->client)) {
            unset($this->client);
        }
    }
}
