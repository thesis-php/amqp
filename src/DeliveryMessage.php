<?php

declare(strict_types=1);

namespace Thesis\Amqp;

use Amp\Future;
use function Amp\async;

/**
 * @api
 * @phpstan-type Ack = \Closure(DeliveryMessage, bool): void
 * @phpstan-type Nack = \Closure(DeliveryMessage, bool, bool): void
 * @phpstan-type Reject = \Closure(DeliveryMessage, bool): void
 */
final class DeliveryMessage
{
    /** @var ?Future<void> */
    private ?Future $processedFuture = null;

    private bool $processed = false;

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
        $this->process(fn() => ($this->ack)($this, $multiple));
    }

    public function nack(bool $multiple = false, bool $requeue = true): void
    {
        $this->process(fn() => ($this->nack)($this, $multiple, $requeue));
    }

    public function reject(bool $requeue = true): void
    {
        $this->process(fn() => ($this->reject)($this, $requeue));
    }

    /**
     * @param \Closure(): void $hook
     */
    private function process(\Closure $hook): void
    {
        $this->processedFuture?->await();

        if (!$this->processed) {
            try {
                /** @var Future<void> $future */
                $future = async($hook);
                $this->processedFuture = $future;

                $this->processedFuture->await();
            } finally {
                $this->processedFuture = null;
            }

            $this->processed = true;
        }
    }
}
