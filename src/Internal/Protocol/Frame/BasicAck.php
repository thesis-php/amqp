<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Protocol\Frame;

use BcMath\Number;
use Thesis\Amqp\Internal\Io;
use Thesis\Amqp\Internal\Protocol\Frame;

/**
 * @internal
 */
final readonly class BasicAck implements Frame
{
    public function __construct(
        public Number $deliveryTag,
        public bool $multiple,
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
            ->writeBits($this->multiple);
    }
}
