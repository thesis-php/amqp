<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Protocol\Frame;

use Thesis\Amqp\Internal\Io;
use Thesis\Amqp\Internal\Protocol\Frame;

/**
 * @internal
 */
final readonly class ExchangeDelete implements Frame
{
    /**
     * @param non-empty-string $exchange
     * @param non-negative-int $reserved1
     */
    public function __construct(
        public string $exchange,
        public bool $ifUnused = false,
        public bool $noWait = false,
        public int $reserved1 = 0,
    ) {}

    public static function read(Io\ReadBytes $reader): self
    {
        $reserved1 = $reader->readUint16();

        $exchange = $reader->readString();
        \assert($exchange !== '', 'exchange must not be empty.');

        [$ifUnused, $noWait] = $reader->readBits(2);

        return new self(
            $exchange,
            $ifUnused,
            $noWait,
            $reserved1,
        );
    }

    public function write(Io\WriteBytes $writer): void
    {
        $writer
            ->writeUint16($this->reserved1)
            ->writeString($this->exchange)
            ->writeBits($this->ifUnused, $this->noWait);
    }
}
