<?php

declare(strict_types=1);

namespace Thesis\Amqp;

/**
 * @api
 * @phpstan-type Ack = \Closure(DeliveryMessage, bool): void
 * @phpstan-type Nack = \Closure(DeliveryMessage, bool, bool): void
 * @phpstan-type Reject = \Closure(DeliveryMessage, bool): void
 */
final class DeliveryMessage
{
    /**
     * @param Ack $ack
     * @param Nack $nack
     * @param Reject $reject
     * @param non-negative-int $deliveryTag
     */
    public function __construct(
        private readonly \Closure $ack,
        private readonly \Closure $nack,
        private readonly \Closure $reject,
        public readonly Message $message,
        public readonly string $exchange = '',
        public readonly string $routingKey = '',
        public readonly int $deliveryTag = 0,
        public readonly string $consumerTag = '',
        public readonly bool $redelivered = false,
        public readonly bool $returned = false,
    ) {}

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
