<?php
declare(strict_types=1);

namespace Memcrab\Queue;

use Monolog\Logger;
use PhpAmqpLib\Channel\AMQPChannel as AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage as AMQPMessage;
use PhpAmqpLib\Connection\AMQPStreamConnection as AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;

class RabbitMQ
{
    private string $environment;
    private Logger $ErrorHandler;
    private bool $connectionError = false;
    private AMQPStreamConnection $client;
    private AMQPChannel $channel;

    private static string $letterExchange = "dlx";
    private static string $letterRoutineKey = "dlrk";

    public function __construct(string $environment, string $host, int $port, string $username, string $password, int $heartbeat, Logger $ErrorHandler)
    {
        $this->environment = $environment;
        $this->ErrorHandler = $ErrorHandler;
        try {
            $this->client = new AMQPStreamConnection($host, $port, $username, $password, heartbeat: $heartbeat);
            $this->channel = $this->client->channel();
        } catch (\Exception $e) {
            throw new \Exception("Cant connect to RabbitMQ. " . $e, 500);
        }
    }

    private function error(\Exception $e): void
    {
        $this->connectionError = $this->checkIsConnectionError($e);
        $this->ErrorHandler->error('RabbitMQ Exception: ' . $e);
    }

    private function checkIsConnectionError(\Exception $e): bool
    {
        return $e instanceof AMQPConnectionClosedException;
    }

    private function getNameWithEnv(string $name): string
    {
        return $this->environment . ':' . $name;
    }

    public function getLetterExchange(): string
    {
        return $this->environment . ':' . self::$letterExchange;
    }

    public function getLetterRoutineKey(string $name): string
    {
        return $this->getNameWithEnv($name) . '-' . self::$letterRoutineKey;
    }

    public function isConnectionError(): bool
    {
        return $this->connectionError;
    }

    public function heartbeat(): void
    {
        $this->client->getConnection()->checkHeartBeat();
        if (!$this->client()->isConnected()) {
            throw new \Exception('RabbitMQ Connection check failed');
        }
    }

    public function close(): void
    {
        try {
            $this->channel->close();
            $this->client->close();
        } catch (\Exception $e) {
            $this->error($e);
            throw $e;
        }
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
        try {
            $name = $this->getNameWithEnv($name);
            $result = $this->channel->queue_declare($name, $passive, $durable, $exclusive, $auto_delete);
        } catch (\Exception $e) {
            $this->error($e);
            throw $e;
        }

        return $result;
    }

    /**
     * @param string $name
     * @param string $type
     * @param bool $passive
     * @param bool $durable
     * @param bool $auto_delete
     *
     * @return mixed|null [type]
     * @throws \Exception
     */
    public function registerExchange(string $name, string $type, bool $passive = false, bool $durable = false, bool $auto_delete = false)
    {
        try {
            $result = $this->channel->exchange_declare($name, $type, $passive, $durable, $auto_delete);
        } catch (\Exception $e) {
            $this->error($e);
            throw $e;
        }

        return $result;
    }

    /**
     * @param string $name
     * @param array $messageBody
     * @param string $exchange
     *
     * @return true [type]
     * @throws \Exception
     */
    public function sendMessage(array $messageBody, string $routingKey, string $exchange = '')
    {
        try {
            if (empty($exchange)) {
                $exchange = $this->getLetterExchange();
            }

            $routingKey = self::getLetterRoutineKey($routingKey);

            $message = new AMQPMessage(
                json_encode($messageBody, JSON_UNESCAPED_UNICODE),
                ['delivery_mode' => 2]
            );
            $this->channel->basic_publish($message, $exchange, $routingKey);
        } catch (\Exception $e) {
            $this->error($e);
            throw $e;
        }

        return true;
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
        bool   $no_local = false,
        bool   $no_ack = false,
        bool   $exclusive = false,
        bool   $nowait = false,
               $callback = null
    )
    {
        try {
            $name = $this->getNameWithEnv($name);
            $result = $this->channel->basic_consume($name, $consumer_tag, $no_local, $no_ack, $exclusive, $nowait, $callback);
            while ($this->channel->is_open()) {
                $this->channel->wait();
            }
        } catch (\Exception $e) {
            $this->error($e);
            throw $e;
        }

        return $result;
    }

    /**
     * @param string $name
     * @param string $exchange
     * @param string $routing_key
     * @return mixed
     */
    public function queueBind(string $queue, string $exchange, string $routing_key)
    {
        try {
            $queue = $this->getNameWithEnv($queue);
            $result = $this->channel->queue_bind($queue, $exchange, $routing_key);
        } catch (\Exception $e) {
            $this->error($e);
            throw $e;
        }

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
     * @return object
     */
    public function channel(): object
    {
        return $this->channel;
    }

    public function __destruct()
    {
        try {
            $this->close();
        } catch (\Throwable $e) {
            $this->ErrorHandler->error('RabbitMQ disconnect error: ' . $e);
        }
    }
}