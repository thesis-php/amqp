<?php

declare(strict_types=1);

namespace Thesis\Amqp;

use Amp\Cancellation;
use Thesis\Sync;

/**
 * @api
 * @phpstan-type Ack = \Closure(DeliveryMessage, bool): void
 * @phpstan-type Nack = \Closure(DeliveryMessage, bool, bool): void
 * @phpstan-type Reject = \Closure(DeliveryMessage, bool): void
 */
final class DeliveryMessage
{
    /** @var ?Sync\Once<void> */
    private ?Sync\Once $processed = null;

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

    public function ack(bool $multiple = false, ?Cancellation $cancellation = null): void
    {
        $this->process(fn() => ($this->ack)($this, $multiple), $cancellation);
    }

    public function nack(bool $multiple = false, bool $requeue = true, ?Cancellation $cancellation = null): void
    {
        $this->process(fn() => ($this->nack)($this, $multiple, $requeue), $cancellation);
    }

    public function reject(bool $requeue = true, ?Cancellation $cancellation = null): void
    {
        $this->process(fn() => ($this->reject)($this, $requeue), $cancellation);
    }

    /**
     * @param \Closure(): void $hook
     */
    private function process(\Closure $hook, ?Cancellation $cancellation = null): void
    {
        ($this->processed ??= new Sync\Once($hook))->await($cancellation);
    }
}
