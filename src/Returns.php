<?php

declare(strict_types=1);

namespace Thesis\Amqp;

use Amp\Pipeline;
use Thesis\Amqp\Internal\Delivery\DeliverySupervisor;

/**
 * @api
 * @template-implements \IteratorAggregate<array-key, DeliveryMessage>
 */
final class Returns implements \IteratorAggregate
{
    /**
     * @internal
     */
    public static function create(DeliverySupervisor $supervisor): self
    {
        return new self($supervisor);
    }

    public function getIterator(): \Traversable
    {
        return $this->iterator->getIterator();
    }

    /** @var Pipeline\ConcurrentIterator<DeliveryMessage> */
    private Pipeline\ConcurrentIterator $iterator;

    /** @var Pipeline\Queue<DeliveryMessage> */
    private Pipeline\Queue $queue;

    private function __construct(
        DeliverySupervisor $supervisor,
    ) {
        /** @var Pipeline\Queue<DeliveryMessage> $queue */
        $queue = new Pipeline\Queue();

        $this->queue = $queue;
        $this->iterator = $queue->iterate();

        $supervisor->addReturnListener($this->queue->push(...));
        $supervisor->addShutdownListener($this->queue->complete(...));
    }
}
