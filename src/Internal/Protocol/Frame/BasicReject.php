<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Protocol\Frame;

use Thesis\Amqp\Internal\Io;
use Thesis\Amqp\Internal\Protocol\Frame;

/**
 * @internal
 */
final readonly class BasicReject implements Frame
{
    /**
     * @param non-negative-int $deliveryTag
     */
    public function __construct(
        public int $deliveryTag,
        public bool $requeue,
    ) {}

    public static function read(Io\ReadBytes $reader): self
    {
        return new self(
            $reader->readUint64(),
            $reader->readBits(1)[0],
        );
    }

    public function write(Io\WriteBytes $writer): void
    {
        $writer
            ->writeUint64($this->deliveryTag)
            ->writeBits($this->requeue);
    }
}
