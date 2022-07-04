<?php

declare(strict_types=1);

namespace Memcrab\Queue;

interface QueueInterface
{
    public static function obj(): self;
    public static function declareConnection(array $properties, \Memcrab\Log\Log $ErrorHandler): void;
    public function connect(): bool;
    public function registerQueue(string $name);
    public function sendMessage(string $name, array $messageBody);
    public function receiveMessage(string $name);
    public function client(): object;
    public function ping(): bool;
    public static function shutdown(): void;
}
