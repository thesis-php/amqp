<?php

declare(strict_types=1);

namespace Thesis\Amqp;

use Thesis\Time\TimeSpan;

/**
 * @api
 */
final readonly class Message
{
    /**
     * @param array<string, mixed> $headers
     * @param ?int<0, 9> $priority
     */
    public function __construct(
        public string $body = '',
        public array $headers = [],
        public ?string $contentType = null,
        public ?string $contentEncoding = null,
        public DeliveryMode $deliveryMode = DeliveryMode::Whatever,
        public ?int $priority = null,
        public ?string $correlationId = null,
        public ?string $replyTo = null,
        public ?TimeSpan $expiration = null,
        public ?string $messageId = null,
        public ?\DateTimeImmutable $timestamp = null,
        public ?string $type = null,
        public ?string $userId = null,
        public ?string $appId = null,
    ) {}
}
