<?php

declare(strict_types=1);

namespace Thesis\Amqp;

/**
 * @api
 *
 * @template-implements \IteratorAggregate<DeliveryMessage>
 */
final class ConsumeBatch implements
    \IteratorAggregate,
    \Countable
{
    private bool $processed = false;

    /**
     * @param non-empty-list<DeliveryMessage> $deliveries
     */
    public function __construct(
        public readonly array $deliveries,
    ) {}

    public function ack(): void
    {
        if (!$this->processed) {
            $this->watermark()->ack(multiple: true);
            $this->processed = true;
        }
    }

    public function nack(bool $requeue = true): void
    {
        if (!$this->processed) {
            $this->watermark()->nack(multiple: true, requeue: $requeue);
            $this->processed = true;
        }
    }

    public function getIterator(): \Traversable
    {
        yield from $this->deliveries;
    }

    public function count(): int
    {
        return \count($this->deliveries);
    }

    private function watermark(): DeliveryMessage
    {
        return $this->deliveries[\count($this->deliveries) - 1];
    }
}
