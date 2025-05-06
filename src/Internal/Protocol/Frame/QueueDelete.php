<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Protocol\Frame;

use Thesis\Amqp\Internal\Io;
use Thesis\Amqp\Internal\Protocol\Frame;

/**
 * @internal
 */
final readonly class QueueDelete implements Frame
{
    /**
     * @param non-empty-string $queue
     * @param non-negative-int $reserved1
     */
    public function __construct(
        public string $queue,
        public bool $ifUnused = false,
        public bool $ifEmpty = false,
        public bool $noWait = false,
        public int $reserved1 = 0,
    ) {}

    public static function read(Io\ReadBytes $reader): self
    {
        $reserved1 = $reader->readUint16();
        $queue = $reader->readString();
        \assert($queue !== '', 'queue must not be empty.');

        [$ifUnused, $ifEmpty, $noWait] = $reader->readBits(3);

        return new self(
            $queue,
            $ifUnused,
            $ifEmpty,
            $noWait,
            $reserved1,
        );
    }

    public function write(Io\WriteBytes $writer): void
    {
        $writer
            ->writeUint16($this->reserved1)
            ->writeString($this->queue)
            ->writeBits($this->ifUnused, $this->ifEmpty, $this->noWait);
    }
}
