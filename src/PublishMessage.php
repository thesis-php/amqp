<?php

declare(strict_types=1);

namespace Thesis\Amqp;

/**
 * @api
 */
final readonly class PublishMessage
{
    public function __construct(
        public Message $message,
        public string $exchange = '',
        public string $routingKey = '',
        public bool $mandatory = false,
        public bool $immediate = false,
    ) {}
}
