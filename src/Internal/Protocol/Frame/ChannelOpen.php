<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Protocol\Frame;

use Thesis\Amqp\Internal\Io;
use Thesis\Amqp\Internal\Protocol\Frame;

/**
 * @internal
 */
final readonly class ChannelOpen implements Frame
{
    public function __construct(
        private string $reserved1 = '',
    ) {}

    public static function read(Io\ReadBytes $reader): self
    {
        return new self($reader->readString());
    }

    public function write(Io\WriteBytes $writer): void
    {
        $writer->writeString($this->reserved1);
    }
}
