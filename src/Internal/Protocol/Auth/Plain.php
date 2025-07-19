<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Protocol\Auth;

use Thesis\Amqp\Internal\Io;

/**
 * @internal
 */
final class Plain extends Mechanism
{
    public function __construct(
        #[\SensitiveParameter]
        private readonly string $username,
        #[\SensitiveParameter]
        private readonly string $password,
    ) {}

    /** @phpstan-ignore-next-line */
    public function name(): string
    {
        return self::PLAIN;
    }

    public function write(Io\WriteBytes $writer): void
    {
        $writer->write("\000{$this->username}\000{$this->password}");
    }
}
