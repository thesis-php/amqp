<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Protocol\Frame;

use Thesis\Amqp\Internal\Io;
use Thesis\Amqp\Internal\Protocol\Frame;

/**
 * @internal
 */
final readonly class QueueDeclare implements Frame
{
    /**
     * @param array<string, mixed> $arguments
     */
    public function __construct(
        public string $queue,
        public bool $passive = false,
        public bool $durable = false,
        public bool $exclusive = false,
        public bool $autoDelete = false,
        public bool $noWait = false,
        public array $arguments = [],
        public int $reserved1 = 0,
    ) {}

    public static function read(Io\ReadBytes $reader): self
    {
        $reserved1 = $reader->readUint16();
        $queue = $reader->readString();

        [$passive, $durable, $exclusive, $autoDelete, $noWait] = $reader->readBits(5);
        $arguments = $reader->readTable();

        return new self(
            $queue,
            $passive,
            $durable,
            $exclusive,
            $autoDelete,
            $noWait,
            $arguments,
            $reserved1,
        );
    }

    public function write(Io\WriteBytes $writer): void
    {
        $writer
            ->writeUint16(0)
            ->writeString($this->queue)
            ->writeBits($this->passive, $this->durable, $this->exclusive, $this->autoDelete, $this->noWait)
            ->writeTable($this->arguments);
    }
}
