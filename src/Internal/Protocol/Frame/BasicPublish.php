<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Protocol\Frame;

use Thesis\Amqp\Internal\Io;
use Thesis\Amqp\Internal\Protocol\Frame;

/**
 * @internal
 */
final readonly class BasicPublish implements Frame
{
    /**
     * @param non-negative-int $reserved1
     */
    public function __construct(
        public string $exchange,
        public string $routingKey,
        public bool $mandatory,
        public bool $immediate,
        public int $reserved1 = 0,
    ) {}

    public static function read(Io\ReadBytes $reader): self
    {
        $reserved1 = $reader->readUint16();
        $exchange = $reader->readString();
        $routingKey = $reader->readString();
        [$mandatory, $immediate] = $reader->readBits(2);

        return new self(
            $exchange,
            $routingKey,
            $mandatory,
            $immediate,
            $reserved1,
        );
    }

    public function write(Io\WriteBytes $writer): void
    {
        $writer
            ->writeUint16($this->reserved1)
            ->writeString($this->exchange)
            ->writeString($this->routingKey)
            ->writeBits($this->mandatory, $this->immediate);
    }
}
