<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Delivery;

use Amp\Pipeline;
use Thesis\Amqp\DeliveryMessage;

/**
 * @internal
 */
final class Receiver
{
    public static function create(DeliverySupervisor $supervisor): self
    {
        $receiver = new self($supervisor);
        $receiver->run();

        return $receiver;
    }

    public function receive(): ?DeliveryMessage
    {
        if (!$this->iterator->continue()) {
            return null;
        }

        return $this->iterator->getValue();
    }

    /** @var Pipeline\ConcurrentIterator<null|DeliveryMessage> */
    private Pipeline\ConcurrentIterator $iterator;

    /** @var Pipeline\Queue<null|DeliveryMessage> */
    private Pipeline\Queue $queue;

    private function __construct(
        private readonly DeliverySupervisor $supervisor,
    ) {
        /** @var Pipeline\Queue<null|DeliveryMessage> $queue */
        $queue = new Pipeline\Queue(bufferSize: 1);

        $this->queue = $queue;
        $this->iterator = $queue->iterate();
    }

    private function run(): void
    {
        $this->supervisor->addGetListener($this->queue->push(...));
        $this->supervisor->addShutdownListener($this->queue->complete(...));
    }
}
