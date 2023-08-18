<?php
declare(strict_types=1);

namespace Memcrab\Queue;

use Monolog\Logger;

class RMQPool extends Pool
{
    private static self $instance;
    private static string $environment;
    private static string $host;
    private static int $port;
    private static string $username;
    private static string $password;

    private function __clone()
    {
    }

    public function __wakeup()
    {
    }

    public static function obj(): self
    {
        return self::$instance;
    }

    public static function declareConnection(
        string $environment,
        string $host,
        int    $port,
        string $username,
        string $password,
        Logger $ErrorHandler,
        int    $waitTimeoutPool,
        int    $capacity = self::DEFAULT_CAPACITY,
    ): void
    {
        self::$instance = new self($capacity);
        self::$instance->setErrorHandler($ErrorHandler);
        self::$instance->setWaitTimeoutPool($waitTimeoutPool);

        self::$environment = $environment;
        self::$host = $host;
        self::$port = $port;
        self::$username = $username;
        self::$password = $password;
    }

    protected function error(\Exception $e): void
    {
        $this->ErrorHandler->error('RMQPool Exception: ' . $e);
    }

    protected function connect(): RabbitMQ|bool
    {
        try {
            return (new RabbitMQ(self::$environment, self::$host, self::$port, self::$username, self::$password, $this->ErrorHandler));
        } catch (\Exception $e) {
            $this->error($e);
            return false;
        }
    }

    protected function disconnect($connection): bool
    {
        try {
            $connection->close();
            return true;
        } catch (\Exception $e) {
            $this->error($e);
            return false;
        }
    }

    protected function checkConnectionForErrors($connection): bool
    {
        return $connection->isConnectionError();
    }

    public function __destruct()
    {
        while (true) {
            if (!$this->isEmpty()) {
                $connection = $this->pop($this->waitTimeoutPool);
                $this->disconnect($connection);
            } else {
                break;
            }
        }
        $this->close();
    }
}