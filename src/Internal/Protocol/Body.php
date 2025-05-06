<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Protocol;

use Thesis\Amqp\Exception\NotImplemented;
use Thesis\Amqp\Internal\Io;

/**
 * @internal
 */
final readonly class Body implements Frame
{
    /**
     * @param non-negative-int $channelId
     */
    public function __construct(
        public int $channelId,
        public string $body,
    ) {}

    public static function read(Io\ReadBytes $reader): self
    {
        throw new NotImplemented();
    }

    public function write(Io\WriteBytes $writer): void
    {
        $writer
            ->writeUint8(FrameType::body->value)
            ->writeUint16($this->channelId)
            ->writeUint32(\strlen($this->body))
            ->write($this->body)
            ->writeUint8(Protocol::FRAME_END);
    }
}
