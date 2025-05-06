<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Protocol\Frame;

use Thesis\Amqp\Internal\Io;
use Thesis\Amqp\Internal\Protocol\Frame;

/**
 * @internal
 */
final readonly class BasicCancel implements Frame
{
    public function __construct(
        public string $consumerTag,
        public bool $noWait,
    ) {}

    public static function read(Io\ReadBytes $reader): self
    {
        return new self(
            $reader->readString(),
            $reader->readBits(1)[0],
        );
    }

    public function write(Io\WriteBytes $writer): void
    {
        $writer
            ->writeString($this->consumerTag)
            ->writeBits($this->noWait);
    }
}
