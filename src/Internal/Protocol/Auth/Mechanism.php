<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Protocol\Auth;

use Thesis\Amqp\Exception\AuthenticationMechanismIsNotSupported;
use Thesis\Amqp\Internal\Io;

/**
 * @internal
 */
abstract class Mechanism
{
    final public const PLAIN = 'PLAIN';
    final public const AMQPLAIN = 'AMQPLAIN';

    /**
     * @param non-empty-string $mechanism
     */
    final public static function create(
        string $mechanism,
        #[\SensitiveParameter]
        string $username,
        #[\SensitiveParameter]
        string $password,
    ): Plain|AMQPlain {
        return match (strtoupper($mechanism)) {
            self::PLAIN => new Plain($username, $password),
            self::AMQPLAIN => new AMQPlain($username, $password),
            default => throw AuthenticationMechanismIsNotSupported::forClientMechanism($mechanism),
        };
    }

    /**
     * @param non-empty-list<self> $selected
     * @param list<string> $available
     * @throws AuthenticationMechanismIsNotSupported
     */
    final public static function select(array $selected, array $available): self
    {
        foreach ($selected as $selectedMechanism) {
            if (\in_array($selectedMechanism->name(), $available, true)) {
                return $selectedMechanism;
            }
        }

        throw AuthenticationMechanismIsNotSupported::forServerMechanisms($available);
    }

    /**
     * @return self::*
     */
    abstract public function name(): string;

    abstract public function write(Io\WriteBytes $writer): void;
}
