<?php

declare(strict_types=1);

namespace Thesis\Amqp;

/**
 * @api
 */
final class PublishMessage
{
    public function __construct(
        public readonly Message $message,
        public readonly string $exchange = '',
        public readonly string $routingKey = '',
        public readonly bool $mandatory = false,
        public readonly bool $immediate = false,
    ) {}
}
