<?php

declare(strict_types=1);

namespace Memcrab\Queue;

interface Queue
{
    public static function obj(): Queue;
    public function setConnectionProperties(array $properties): array;
    public function connect(): Queue;
    public function registerQueue(string $name);
    public function sendMessage(string $name, array $messageBody);
    public function receiveMessage(string $name);
    public function client(): object;
    public static function shutdown(): void;
}
