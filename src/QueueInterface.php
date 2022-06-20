<?php

declare(strict_types=1);

namespace Memcrab\Queue;

interface QueueInterface
{
    public static function obj(): self;
    public static function setConnectionProperties(array $properties): void;
    public function connect(): self;
    public function registerQueue(string $name);
    public function sendMessage(string $name, array $messageBody);
    public function receiveMessage(string $name);
    public function client(): object;
    public function connectionStatus(): bool;
    public static function shutdown(): void;
}
