<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Protocol\Auth;

use Thesis\Amqp\Internal\Io;

/**
 * @internal
 */
final class AMQPlain extends Mechanism
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
        return self::AMQPLAIN;
    }

    public function write(Io\WriteBytes $writer): void
    {
        $writer
            ->writeString('LOGIN')
            ->writeValue($this->username)
            ->writeString('PASSWORD')
            ->writeValue($this->password);
    }
}
