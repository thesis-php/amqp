<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Protocol\Frame;

use Thesis\Amqp\Internal\Io;
use Thesis\Amqp\Internal\Protocol\Frame;

/**
 * @internal
 */
final readonly class QueueUnbind implements Frame
{
    /**
     * @param non-empty-string $queue
     * @param array<string, mixed> $arguments
     * @param non-negative-int $reserved1
     */
    public function __construct(
        public string $queue,
        public string $exchange,
        public string $routingKey,
        public array $arguments = [],
        public int $reserved1 = 0,
    ) {}

    public static function read(Io\ReadBytes $reader): self
    {
        $reserved1 = $reader->readUint16();
        $queue = $reader->readString();
        \assert($queue !== '', 'queue must not be empty.');

        $exchange = $reader->readString();
        $routingKey = $reader->readString();
        $arguments = $reader->readTable();

        return new self(
            $queue,
            $exchange,
            $routingKey,
            $arguments,
            $reserved1,
        );
    }

    public function write(Io\WriteBytes $writer): void
    {
        $writer
            ->writeUint16($this->reserved1)
            ->writeString($this->queue)
            ->writeString($this->exchange)
            ->writeString($this->routingKey)
            ->writeTable($this->arguments);
    }
}
