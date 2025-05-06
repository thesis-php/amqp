<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Protocol\Frame;

use Thesis\Amqp\Internal\Io;
use Thesis\Amqp\Internal\Protocol\Frame;

/**
 * @internal
 */
final readonly class QueueBind implements Frame
{
    /**
     * @param array<string, mixed> $arguments
     */
    public function __construct(
        public string $queue,
        public string $exchange,
        public string $routingKey,
        public array $arguments = [],
        public bool $noWait = false,
        public int $reserved1 = 0,
    ) {}

    public static function read(Io\ReadBytes $reader): self
    {
        $reserved1 = $reader->readUint16();
        $queue = $reader->readString();
        $exchange = $reader->readString();
        $routingKey = $reader->readString();
        $noWait = $reader->readBits(1)[0];
        $arguments = $reader->readTable();

        return new self(
            $queue,
            $exchange,
            $routingKey,
            $arguments,
            $noWait,
            $reserved1,
        );
    }

    public function write(Io\WriteBytes $writer): void
    {
        $writer
            ->writeInt16(0)
            ->writeString($this->queue)
            ->writeString($this->exchange)
            ->writeString($this->routingKey)
            ->writeBits($this->noWait)
            ->writeTable($this->arguments);
    }
}
