<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Protocol\Frame;

use Thesis\Amqp\Internal\Io;
use Thesis\Amqp\Internal\Protocol\Frame;

/**
 * @internal
 */
final readonly class QueueDeclareOk implements Frame
{
    /**
     * @param non-empty-string $queue
     * @param non-negative-int $messages
     * @param non-negative-int $consumers
     */
    public function __construct(
        public string $queue,
        public int $messages,
        public int $consumers,
    ) {}

    public static function read(Io\ReadBytes $reader): self
    {
        /** @var non-empty-string $queue */
        $queue = $reader->readString();

        /** @var non-negative-int $messages */
        $messages = $reader->readInt32();

        /** @var non-negative-int $consumers */
        $consumers = $reader->readInt32();

        return new self(
            $queue,
            $messages,
            $consumers,
        );
    }

    public function write(Io\WriteBytes $writer): void
    {
        $writer
            ->writeString($this->queue)
            ->writeInt32($this->messages)
            ->writeInt32($this->consumers);
    }
}
