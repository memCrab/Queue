<?php

declare(strict_types=1);

namespace Memcrab\Queue;

use Aws\Sqs\SqsClient;
use Aws\Exception\AwsException;

class SQS implements Queue
{

    private static SQS $instance;
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
     * @param  string  $region
     * @param  string  $key
     * @param  string  $secret
     * @param  string  $version
     * @param  string  $endpoint
     * @return mixed
     */
    public function setConnect(string $region, string $key, string $secret, string $version, ?string $endpoint): Queue
    {
        try {
            $config = [
                'region' => $region,
                'version' => $version,
                'credentials' => [
                    'key' => $key,
                    'secret' => $secret,
                ]
            ];

            if ($endpoint != null && \mb_strlen(trim($endpoint)) > 0) {
                $config['endpoint'] = $endpoint;
            }

            $this->client = new SqsClient($config);
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
     * @param  array   $massageBody
     * @param  array   $attributes
     * @param  int     $delaySeconds
     * @return mixed
     */
    public function sendMessage(string $name, array $massageBody, ?array $attributes = [], int $delaySeconds = 10)
    {
        try {

            $result = $this->client->sendMessage([
                'DelaySeconds' => $delaySeconds,
                'MessageAttributes' => $attributes,
                'MessageBody' => serialize($massageBody),
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
        if(isset(self::$instance->client)) {
            self::$instance->client->destroy();
            unset(self::$instance->client);
        }
    }

    public function __destruct()
    {
        if(!empty($this->client)) {
            $this->client->destroy();
        }
    }
}
