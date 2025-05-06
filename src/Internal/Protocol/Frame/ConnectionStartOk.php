<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Protocol\Frame;

use Thesis\Amqp\Internal\Io;
use Thesis\Amqp\Internal\Protocol\Auth\Mechanism;
use Thesis\Amqp\Internal\Protocol\Frame;
use Thesis\Endian\endian;

/**
 * @internal
 */
final readonly class ConnectionStartOk implements Frame
{
    /**
     * @param array<string, mixed> $clientProperties
     */
    public function __construct(
        public array $clientProperties,
        public Mechanism $auth,
        public string $locale = 'en_US',
    ) {}

    public static function read(Io\ReadBytes $reader): self
    {
        throw new \BadMethodCallException('Not implemented yet.');
    }

    public function write(Io\WriteBytes $writer): void
    {
        $writer
            ->writeTable($this->clientProperties)
            ->writeString($this->auth->name())
            ->reserve(endian::network->packUint32(...), $this->auth->write(...))
            ->writeString($this->locale);
    }
}
