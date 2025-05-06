<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Protocol;

use Thesis\Amqp\Exception\NotImplemented;
use Thesis\Amqp\Internal\Io;
use Thesis\Amqp\Internal\MessageProperties;

/**
 * @internal
 */
final readonly class Header implements Frame
{
    /**
     * @param non-negative-int $channelId
     * @param ClassType::* $classId
     * @param non-negative-int $weight
     */
    public function __construct(
        public int $channelId,
        public int $classId,
        public MessageProperties $properties,
        public int $weight = 0,
    ) {}

    public static function read(Io\ReadBytes $reader): self
    {
        throw new NotImplemented();
    }

    public function write(Io\WriteBytes $writer): void
    {
        $writer = $writer
            ->writeUint8(FrameType::header->value)
            ->writeUint16($this->channelId)
            ->writeUint32(14 + $this->properties->size())
            ->writeUint16($this->classId)
            ->writeUint16($this->weight)
            ->writeUint64($this->properties->bodyLen)
            ->writeUint16($mask = $this->properties->mask());

        $this->properties
            ->write($writer, $mask)
            ->writeUint8(Protocol::FRAME_END);
    }
}
