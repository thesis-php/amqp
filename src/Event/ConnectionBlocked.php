<?php

declare(strict_types=1);

namespace Thesis\Amqp\Event;

final readonly class ConnectionBlocked
{
    public function __construct(
        public string $reason,
    ) {}
}
