<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Batch;

use Amp\Cancellation;
use Amp\CancelledException;
use Amp\CompositeCancellation;
use Amp\TimeoutCancellation;
use Thesis\Amqp\Channel;
use Thesis\Amqp\ConsumeBatch;
use Thesis\Amqp\DeliveryMessage;
use Thesis\Amqp\Iterator;

/**
 * @internal
 */
final class BatchConsumer
{
    public function __construct(
        private readonly Channel $channel,
        private readonly ConsumeBatchOptions $options,
        private readonly Cancellation $cancellation,
    ) {}

    /**
     * @param Iterator<DeliveryMessage> $iterator
     * @param callable(ConsumeBatch, Channel): void $callback
     * @throws \Throwable
     */
    public function consume(
        Iterator $iterator,
        callable $callback,
    ): void {
        while (!$this->cancellation->isRequested()) {
            $deliveries = $this->awaitDeliveries($iterator, $this->cancellation);

            if (\count($deliveries) > 0) {
                $callback(new ConsumeBatch($deliveries), $this->channel);
            }
        }
    }

    /**
     * @param Iterator<DeliveryMessage> $iterator
     * @return list<DeliveryMessage>
     */
    private function awaitDeliveries(Iterator $iterator, Cancellation $consumerCancellation): array
    {
        $cancellations = [$consumerCancellation];

        if ($this->options->timeout !== null) {
            $cancellations[] = new TimeoutCancellation($this->options->timeout);
        }

        $deliveryCancellation = new CompositeCancellation(...$cancellations);

        /** @var list<DeliveryMessage> $deliveries */
        $deliveries = [];

        try {
            while ($iterator->continue($deliveryCancellation)) {
                $deliveries[] = $iterator->value();
                if (\count($deliveries) === $this->options->count) {
                    break;
                }
            }
        } catch (CancelledException) {
        }

        return $deliveries;
    }
}
