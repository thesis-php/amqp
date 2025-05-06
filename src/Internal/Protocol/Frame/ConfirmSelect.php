<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Protocol\Frame;

use Thesis\Amqp\Internal\Io;
use Thesis\Amqp\Internal\Protocol\Frame;

/**
 * @internal
 */
final readonly class ConfirmSelect implements Frame
{
    public function __construct(
        public bool $noWait = false,
    ) {}

    public static function read(Io\ReadBytes $reader): Frame
    {
        return new self($reader->readBits(1)[0]);
    }

    public function write(Io\WriteBytes $writer): void
    {
        $writer->writeBits($this->noWait);
    }
}
