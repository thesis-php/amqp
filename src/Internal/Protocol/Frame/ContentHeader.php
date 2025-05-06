<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Protocol\Frame;

use Thesis\Amqp\Exception\NotImplemented;
use Thesis\Amqp\Internal\Io;
use Thesis\Amqp\Internal\MessageProperties;
use Thesis\Amqp\Internal\Protocol\Frame;

/**
 * @internal
 */
final readonly class ContentHeader implements Frame
{
    /**
     * @param non-negative-int $classId
     * @param non-negative-int $weight
     * @param non-negative-int $bodySize
     * @param non-negative-int $flags
     */
    public function __construct(
        public int $classId,
        public int $weight,
        public int $bodySize,
        public int $flags,
        public MessageProperties $properties,
    ) {}

    public static function read(Io\ReadBytes $reader): self
    {
        return new self(
            $reader->readUint16(),
            $reader->readUint16(),
            $reader->readUint64(),
            $flags = $reader->readUint16(),
            MessageProperties::read($reader, $flags),
        );
    }

    public function write(Io\WriteBytes $writer): void
    {
        throw new NotImplemented();
    }
}
