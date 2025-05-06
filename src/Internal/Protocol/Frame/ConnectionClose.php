<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Protocol\Frame;

use Thesis\Amqp\Internal\Io;
use Thesis\Amqp\Internal\Protocol\Frame;

/**
 * @internal
 */
final readonly class ConnectionClose implements Frame
{
    private const int REPLYSUCCESS = 200;

    /**
     * @param non-negative-int $replyCode
     * @param non-negative-int $classId
     * @param non-negative-int $methodId
     */
    public function __construct(
        public int $replyCode = self::REPLYSUCCESS,
        public string $replyText = '',
        public int $classId = 0,
        public int $methodId = 0,
    ) {}

    public static function read(Io\ReadBytes $reader): self
    {
        return new self(
            $reader->readUint16(),
            $reader->readString(),
            $reader->readUint16(),
            $reader->readUint16(),
        );
    }

    public function write(Io\WriteBytes $writer): void
    {
        $writer
            ->writeUint16($this->replyCode)
            ->writeString($this->replyText)
            ->writeUint16($this->classId)
            ->writeUint16($this->methodId);
    }
}
