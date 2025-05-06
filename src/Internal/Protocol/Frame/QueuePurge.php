<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Protocol\Frame;

use Thesis\Amqp\Internal\Io;
use Thesis\Amqp\Internal\Protocol\Frame;

/**
 * @internal
 */
final readonly class QueuePurge implements Frame
{
    /**
     * @param non-empty-string $queue
     * @param non-negative-int $reserved1
     */
    public function __construct(
        public string $queue,
        public bool $noWait = false,
        public int $reserved1 = 0,
    ) {}

    public static function read(Io\ReadBytes $reader): self
    {
        $reserved1 = $reader->readUint16();
        $queue = $reader->readString();
        \assert($queue !== '', 'queue must not be empty');

        $noWait = $reader->readBits(1)[0];

        return new self(
            $queue,
            $noWait,
            $reserved1,
        );
    }

    public function write(Io\WriteBytes $writer): void
    {
        $writer
            ->writeUint16($this->reserved1)
            ->writeString($this->queue)
            ->writeBits($this->noWait);
    }
}
