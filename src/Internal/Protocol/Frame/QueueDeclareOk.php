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
        $queue = $reader->readString();
        \assert($queue !== '');

        $messages = $reader->readInt32();
        \assert($messages >= 0);

        $consumers = $reader->readInt32();
        \assert($consumers >= 0);

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
