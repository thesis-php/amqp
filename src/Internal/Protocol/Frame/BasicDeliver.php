<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Protocol\Frame;

use Thesis\Amqp\Internal\Io;
use Thesis\Amqp\Internal\Protocol\Frame;

/**
 * @internal
 */
final readonly class BasicDeliver implements Frame
{
    /**
     * @param non-negative-int $deliveryTag
     */
    public function __construct(
        public string $consumerTag,
        public int $deliveryTag,
        public bool $redelivered,
        public string $exchange,
        public string $routingKey,
    ) {}

    public static function read(Io\ReadBytes $reader): self
    {
        return new self(
            $reader->readString(),
            $reader->readUint64(),
            $reader->readBits(1)[0],
            $reader->readString(),
            $reader->readString(),
        );
    }

    public function write(Io\WriteBytes $writer): void
    {
        $writer
            ->writeString($this->consumerTag)
            ->writeUint64($this->deliveryTag)
            ->writeBits($this->redelivered)
            ->writeString($this->exchange)
            ->writeString($this->routingKey);
    }
}
