<?php

declare(strict_types=1);

namespace Thesis\Amqp\Exception;

use Thesis\Amqp\AmqpException;

/**
 * @api
 */
final class ConnectionIsBlocked extends \RuntimeException implements AmqpException
{
    public function __construct(
        public readonly string $reason,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            message: 'Connection is blocked: ' . $reason,
            previous: $previous,
        );
    }
}
