<?php

declare(strict_types=1);

namespace Thesis\Amqp;

/**
 * @api
 */
final class Message
{
    /**
     * @param array<string, mixed> $headers
     * @param ?int<0, 9> $priority
     */
    public function __construct(
        public readonly string $body = '',
        public readonly array $headers = [],
        public readonly ?string $contentType = null,
        public readonly ?string $contentEncoding = null,
        public readonly DeliveryMode $deliveryMode = DeliveryMode::Whatever,
        public readonly ?int $priority = null,
        public readonly ?string $correlationId = null,
        public readonly ?string $replyTo = null,
        public readonly ?string $expiration = null,
        public readonly ?string $messageId = null,
        public readonly ?\DateTimeImmutable $timestamp = null,
        public readonly ?string $type = null,
        public readonly ?string $userId = null,
        public readonly ?string $appId = null,
    ) {}
}
