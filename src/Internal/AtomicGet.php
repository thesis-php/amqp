<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal;

use Amp\Cancellation;
use Amp\Future;
use Thesis\Amqp\DeliveryMessage;
use Thesis\Amqp\Internal\Delivery\Receiver;
use Thesis\Amqp\Internal\Io\AmqpConnection;
use function Amp\async;

/**
 * @internal
 */
final class AtomicGet
{
    /** @var ?Future<null|DeliveryMessage> */
    private ?Future $future = null;

    /**
     * @param non-negative-int $channelId
     */
    public function __construct(
        private readonly Receiver $receiver,
        private readonly AmqpConnection $connection,
        private readonly int $channelId,
    ) {}

    public function receive(string $queue = '', bool $noAck = false, ?Cancellation $cancellation = null): ?DeliveryMessage
    {
        while ($this->future !== null) {
            $this->future->await($cancellation);
        }

        try {
            return ($this->future = async(function () use ($queue, $noAck): ?DeliveryMessage {
                $this->connection->writeFrame(Protocol\Method::basicGet(
                    channelId: $this->channelId,
                    queue: $queue,
                    noAck: $noAck,
                ));

                return $this->receiver->receive();
            }))->await($cancellation);
        } finally {
            $this->future = null;
        }
    }
}
