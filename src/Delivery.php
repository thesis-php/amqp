<?php

declare(strict_types=1);

namespace Thesis\Amqp;

/**
 * @api
 * @phpstan-type Ack = callable(Delivery, bool): void
 * @phpstan-type Nack = callable(Delivery, bool, bool): void
 * @phpstan-type Reject = callable(Delivery, bool): void
 */
final class Delivery
{
    /** @var Ack */
    private $ack;

    /** @var Nack */
    private $nack;

    /** @var Reject */
    private $reject;

    /**
     * @param Ack $ack
     * @param Nack $nack
     * @param Reject $reject
     * @param array<string, mixed> $headers
     * @param non-negative-int $deliveryTag
     * @param ?int<0, 9> $priority
     */
    public function __construct(
        callable $ack,
        callable $nack,
        callable $reject,
        public readonly string $body = '',
        public readonly string $exchange = '',
        public readonly string $routingKey = '',
        public readonly array $headers = [],
        public readonly int $deliveryTag = 0,
        public readonly string $consumerTag = '',
        public readonly bool $redelivered = false,
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
        public readonly bool $returned = false,
    ) {
        $this->ack = $ack;
        $this->nack = $nack;
        $this->reject = $reject;
    }

    public function ack(bool $multiple = false): void
    {
        ($this->ack)($this, $multiple);
    }

    public function nack(bool $multiple = false, bool $requeue = true): void
    {
        ($this->nack)($this, $multiple, $requeue);
    }

    public function reject(bool $requeue = true): void
    {
        ($this->reject)($this, $requeue);
    }
}
