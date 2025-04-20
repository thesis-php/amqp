<?php

declare(strict_types=1);

namespace Thesis\Amqp;

use Amp\Cancellation;
use Amp\CancelledException;
use Amp\DeferredCancellation;
use Revolt\EventLoop;

/**
 * @api
 */
final class BatchConsumer
{
    public function __construct(
        private readonly Channel $channel,
        private readonly ConsumeBatchOptions $options,
    ) {}

    /**
     * @param callable(ConsumeBatch, Channel): void $callback
     * @param array<string, mixed> $arguments
     * @throws \Throwable
     */
    public function consume(
        callable $callback,
        string $queue = '',
        string $consumerTag = '',
        bool $noLocal = false,
        bool $noAck = false,
        bool $exclusive = false,
        bool $noWait = false,
        array $arguments = [],
    ): Canceller {
        $this->channel->qos(
            prefetchCount: $this->options->count,
            global: $this->options->global,
        );

        $iterator = $this->channel->consumeIterator(
            queue: $queue,
            consumerTag: $consumerTag,
            noLocal: $noLocal,
            noAck: $noAck,
            exclusive: $exclusive,
            noWait: $noWait,
            arguments: $arguments,
        );

        $canceller = new Canceller(
            $iterator->complete(...),
            $iterator->cancel(...),
        );

        EventLoop::queue(
            $this->consumeFromIterator(...),
            $iterator,
            $callback,
            $canceller->cancellation(),
        );

        return $canceller;
    }

    /**
     * @param callable(ConsumeBatch, Channel): void $callback
     */
    private function consumeFromIterator(
        Iterator $iterator,
        callable $callback,
        Cancellation $cancellation,
    ): void {
        try {
            while (!$cancellation->isRequested()) {
                $deliveries = $this->await($iterator, $cancellation);

                if (\count($deliveries) > 0) {
                    $batch = new ConsumeBatch($deliveries);

                    try {
                        $callback($batch, $this->channel);
                        $batch->ack();
                    } catch (\Throwable) {
                        $batch->nack();
                    }
                }
            }
        } catch (CancelledException) {
            // no-op.
        }
    }

    /**
     * @return list<DeliveryMessage>
     */
    private function await(Iterator $iterator, Cancellation $consumerCancellation): array
    {
        $deferred = new DeferredCancellation();
        $deliveryCancellation = $deferred->getCancellation();

        $cancellationCallbackId = $consumerCancellation->subscribe($deferred->cancel(...));

        if ($this->options->timeout !== null) {
            $delayCallbackId = EventLoop::delay($this->options->timeout, static fn() => $deferred->cancel());
        }

        /** @var list<DeliveryMessage> $deliveries */
        $deliveries = [];

        try {
            while ($iterator->continue($deliveryCancellation)) {
                $deliveries[] = $iterator->value();
                if (\count($deliveries) === $this->options->count) {
                    break;
                }
            }
        } catch (CancelledException $e) {
            if (!$deliveryCancellation->isRequested()) {
                throw $e;
            }
        } finally {
            $consumerCancellation->unsubscribe($cancellationCallbackId);

            if (isset($delayCallbackId)) {
                EventLoop::cancel($delayCallbackId);
            }
        }

        return $deliveries;
    }
}
