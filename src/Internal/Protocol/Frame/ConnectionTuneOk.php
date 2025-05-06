<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Protocol\Frame;

use Thesis\Amqp\Internal\Io;
use Thesis\Amqp\Internal\Protocol\Frame;

/**
 * @internal
 */
final readonly class ConnectionTuneOk implements Frame
{
    /**
     * @param non-negative-int $channelMax
     * @param non-negative-int $frameMax
     * @param non-negative-int $heartbeat
     */
    public function __construct(
        public int $channelMax,
        public int $frameMax,
        public int $heartbeat,
    ) {}

    public static function read(Io\ReadBytes $reader): self
    {
        return new self(
            $reader->readUint16(),
            $reader->readUint32(),
            $reader->readUint16(),
        );
    }

    public function write(Io\WriteBytes $writer): void
    {
        $writer
            ->writeUint16($this->channelMax)
            ->writeUint32($this->frameMax)
            ->writeUint16($this->heartbeat);
    }
}
