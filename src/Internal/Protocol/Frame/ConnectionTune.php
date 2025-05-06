<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Protocol\Frame;

use Thesis\Amqp\Internal\Io;
use Thesis\Amqp\Internal\Protocol\Frame;

/**
 * @internal
 */
final readonly class ConnectionTune implements Frame
{
    public function __construct(
        public int $channelMax,
        public int $frameMax,
        public int $heartbeat,
    ) {}

    public static function read(Io\ReadBytes $reader): self
    {
        return new self(
            $reader->readInt16(),
            $reader->readInt32(),
            $reader->readInt16(),
        );
    }

    public function write(Io\WriteBytes $writer): void
    {
        $writer
            ->writeInt16($this->channelMax)
            ->writeInt32($this->frameMax)
            ->writeInt16($this->heartbeat);
    }
}
