<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Protocol\Frame;

use Thesis\Amqp\Internal\Io;
use Thesis\Amqp\Internal\Protocol\Frame;

/**
 * @internal
 */
final readonly class ConnectionOpen implements Frame
{
    /**
     * @param non-empty-string $vhost
     */
    public function __construct(
        public string $vhost,
        public string $reserved1 = '',
        public bool $reserved2 = false,
    ) {}

    public static function read(Io\ReadBytes $reader): self
    {
        $vhost = $reader->readString();
        \assert($vhost !== '', 'vhost must not be empty.');

        return new self(
            $vhost,
            $reader->readString(),
            $reader->readBits(1)[0],
        );
    }

    public function write(Io\WriteBytes $writer): void
    {
        $writer
            ->writeString($this->vhost)
            ->writeString($this->reserved1)
            ->writeBits($this->reserved2);
    }
}
