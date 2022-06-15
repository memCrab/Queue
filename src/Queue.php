<?php

declare(strict_types=1);

namespace Memcrab\Queue;

interface Queue
{
    public static function obj(): Queue;
    public function setConnect(string $region, string $key, string $secret, string $version, ?string $endpoint): Queue;
    public function registerQueue(string $name, ?array $atributes = []): Queue;
    public function changeMessageVisibility(string $name, array $message, int $VisibilityTimeout): Queue;
    public function sendMessage(string $name, array $massageBody, ?array $attributes = [], int $delaySeconds = 10);
    public function receiveMessage(string $name);
    public function deleteMessage(string $name, array $message);
    public function client(): object;
    public function getQueueUrl(string $queueName): string;
    public static function shutdown(): void;
}
