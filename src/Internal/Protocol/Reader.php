<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Protocol;

use Thesis\Amqp\Exception\FrameIsBroken;
use Thesis\Amqp\Internal\Io;
use Thesis\ByteOrder\ReadFrom;

/**
 * @internal
 */
final readonly class Reader
{
    /** @var int */
    private const int HEADER_SIZE = 7;

    private Io\Buffer $buffer;

    public function __construct(
        private ReadFrom $reader,
    ) {
        $this->buffer = Io\Buffer::empty();
    }

    /**
     * @throws \Throwable
     */
    public function read(): Request
    {
        $this->buffer->write($this->reader->read(self::HEADER_SIZE));

        $type = FrameType::from($this->buffer->readUint8());
        $channelId = $this->buffer->readUint16();

        if (($size = $this->buffer->readUint32()) > 0) {
            $this->buffer->write($this->reader->read($size));
        }

        $frame = match ($type) {
            FrameType::method => Protocol::amqp091->parseMethod($this->buffer),
            FrameType::header => Protocol::amqp091->parseHeader($this->buffer),
            FrameType::body => Protocol::amqp091->parseBody($this->buffer),
            FrameType::heartbeat => Heartbeat::frame,
        };

        if ($this->reader->readUint8() !== Protocol::FRAME_END) {
            throw new FrameIsBroken();
        }

        return new Request($channelId, $frame);
    }
}
