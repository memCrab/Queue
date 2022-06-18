<?php

declare(strict_types=1);

namespace Memcrab\Queue;

use Aws\Sqs\SqsClient;
use Aws\Exception\AwsException;

class SQS implements Queue
{

    private static SQS $instance;
    private $config;
    private $client;
    private array $urls;

    public function __construct()
    {
    }
    public function __clone()
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
    public function setConnectionProperties(array $properties): array
    {
        $this->config = [
            'region' => $properties[0],
            'version' => $properties[3],
            'credentials' => [
                'key' => $properties[1],
                'secret' => $properties[2],
            ]
        ];

        if ($properties[4] != null && \mb_strlen(trim($properties[4])) > 0) {
            $this->config['endpoint'] = $properties[4];
        }

        return $this->config;
    }

    /**
     * @return Queue
     */
    public function connect(): Queue
    {
        try {
            $this->client = new SqsClient($this->config);
        } catch (AwsException $e) {
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
        } catch (AwsException $e) {
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
        } catch (AwsException $e) {
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
        } catch (AwsException $e) {
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
        } catch (AwsException $e) {
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
        } catch (AwsException $e) {
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
        self::$instance->client->destroy();
        unset(self::$instance->client);
    }

    public function __destruct()
    {
        $this->client->destroy();
    }
}
