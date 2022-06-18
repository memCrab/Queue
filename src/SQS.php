<?php

declare(strict_types=1);

namespace Memcrab\Queue;

use Aws\Sqs\SqsClient;

class SQS implements Queue
{
    private $client;
    private array $urls;
    private static SQS $instance;
    private static string $region;
    private static string $version;
    private static string $endpoint;
    private static string $key;
    private static string $secret;

    private function __construct()
    {
    }
    private function __clone()
    {
    }
    public function __wakeup()
    {
    }

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
        try {
            if (!isset($properties['region']) || empty($properties['region']) || !is_string($properties['region'])) {
                throw new \Exception("Aws `region` property need to be string");
            }
            if (!isset($properties['version']) || empty($properties['version']) || !is_string($properties['version'])) {
                throw new \Exception("Aws `version` property need to be string");
            }
            if (!isset($properties['endpoint']) || empty($properties['endpoint']) || !is_string($properties['endpoint'])) {
                throw new \Exception("Aws `endpoint` property need to be string");
            }
            if (!isset($properties['key']) || empty($properties['key']) || !is_string($properties['key'])) {
                throw new \Exception("Aws `key` property need to be string");
            }
            if (!isset($properties['secret']) || empty($properties['secret']) || !is_string($properties['secret'])) {
                throw new \Exception("Aws `secret` property need to be string");
            }

            self::$region = $properties['region'];
            self::$version = $properties['version'];
            self::$endpoint = $properties['endpoint'];
            self::$key = $properties['key'];
            self::$secret = $properties['secret'];
        } catch (\Exception $e) {
            error_log((string) $e);
            throw $e;
        }
    }

    /**
     * @return Queue
     */
    public function connect(): Queue
    {
        try {
            $this->client = new SqsClient(
                [
                    'region' => self::$region,
                    'version' => self::$version,
                    'endpoint' => self::$endpoint,
                    'credentials' => [
                        'key' => self::$key,
                        'secret' => self::$secret,
                    ]
                ]
            );
        } catch (\Exception $e) {
            error_log((string) $e);
            throw $e;
        }

        return $this;
    }

    /**
     * @param  string  $name
     * @param  array   $atributes
     * @return mixed
     */
    public function registerQueue(string $name, ?array $atributes = []): Queue
    {
        try {

            $result = $this->client->createQueue([
                'QueueName' => $name,
                'Attributes' => $atributes,
            ]);
            $this->urls[$name] = $result->get('QueueUrl');
        } catch (\Exception $e) {
            error_log((string) $e);
            throw $e;
        }

        return $this;
    }

    /**
     * @param  string  $name
     * @param  array   $message
     * @param  int     $VisibilityTimeout
     * @return mixed
     */
    public function changeMessageVisibility(string $name, array $message, int $VisibilityTimeout): Queue
    {
        try {

            $result = $this->client->changeMessageVisibility([
                'QueueUrl' => $this->urls[$name],
                'ReceiptHandle' => $message['ReceiptHandle'],
                'VisibilityTimeout' => $VisibilityTimeout,
            ]);
        } catch (\Exception $e) {
            error_log((string) $e);
            throw $e;
        }

        return $this;
    }

    /**
     * @param  string  $name
     * @param  array   $messageBody
     * @param  array   $attributes
     * @param  int     $delaySeconds
     * @return mixed
     */
    public function sendMessage(string $name, array $messageBody, ?array $attributes = [], int $delaySeconds = 10)
    {
        try {

            $result = $this->client->sendMessage([
                'DelaySeconds' => $delaySeconds,
                'MessageAttributes' => $attributes,
                'MessageBody' => serialize($messageBody),
                'QueueUrl' => $this->urls[$name],
            ]);
        } catch (\Exception $e) {
            error_log((string) $e);
            throw $e;
        }
        return $result;
    }

    /**
     * @param  string  $name
     * @return mixed
     */
    public function receiveMessage(string $name)
    {
        try {
            $result = $this->client->receiveMessage([
                'AttributeNames' => ['SentTimestamp', 'ApproximateReceiveCount'],
                'MaxNumberOfMessages' => 1,
                'MessageAttributeNames' => ['All'],
                'QueueUrl' => $this->urls[$name], // REQUIRED
                'WaitTimeSeconds' => 20,
            ]);
        } catch (\Exception $e) {
            error_log((string) $e);
            throw $e;
        }
        return $result;
    }

    /**
     * @param  string  $name
     * @param  array   $message
     * @return mixed
     */
    public function deleteMessage(string $name, array $message)
    {
        try {
            $result = $this->client->deleteMessage([
                'QueueUrl' => $this->urls[$name], // REQUIRED
                'ReceiptHandle' => $message['ReceiptHandle'], // REQUIRED
            ]);
        } catch (\Exception $e) {
            error_log((string) $e);
            throw $e;
        }
        return $result;
    }

    public function client(): object
    {
        return $this->client;
    }

    /**
     * @param  string  $queueName
     */
    public function getQueueUrl(string $queueName): string
    {
        return $this->urls[$queueName];
    }

    public static function shutdown(): void
    {
        if (isset(self::$instance->client)) {
            self::$instance->client->destroy();
            unset(self::$instance->client);
        }
    }

    public function __destruct()
    {
        if (!empty($this->client)) {
            $this->client->destroy();
        }
    }
}
