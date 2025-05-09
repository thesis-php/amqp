<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Delivery;

use Amp\Pipeline;
use Thesis\Amqp\DeliveryMessage;

/**
 * @internal
 */
final readonly class Receiver
{
    /** @var Pipeline\ConcurrentIterator<null|DeliveryMessage> */
    private Pipeline\ConcurrentIterator $iterator;

    public function __construct(DeliverySupervisor $supervisor)
    {
        /** @var Pipeline\Queue<null|DeliveryMessage> $queue */
        $queue = new Pipeline\Queue(bufferSize: 1);
        $this->iterator = $queue->iterate();

        $supervisor->addGetListener($queue->push(...));
        $supervisor->addShutdownListener($queue->complete(...));
    }

    public function receive(): ?DeliveryMessage
    {
        return $this->iterator->continue()
            ? $this->iterator->getValue()
            : null;
    }
}
