<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Delivery;

use Thesis\Amqp\Channel;
use Thesis\Amqp\DeliveryMessage;

/**
 * @internal
 * @phpstan-type Listener = callable(DeliveryMessage, Channel): void
 */
final class Consumer
{
    /** @var array<non-empty-string, Listener> */
    private array $consumers = [];

    public function __construct(DeliverySupervisor $supervisor)
    {
        $consumers = &$this->consumers;

        $supervisor->addConsumeListener(static function (DeliveryMessage $delivery, Channel $channel) use (&$consumers): void {
            $consumer = $consumers[$delivery->consumerTag] ?? null;
            if ($consumer !== null) {
                $consumer($delivery, $channel);
            }
        });

        $supervisor->addShutdownListener(static function () use (&$consumers): void {
            $consumers = [];
        });
    }

    /**
     * @param non-empty-string $consumerTag
     * @param Listener $consumer
     */
    public function register(string $consumerTag, callable $consumer): void
    {
        $this->consumers[$consumerTag] = $consumer;
    }

    /**
     * @param non-empty-string $consumerTag
     */
    public function unregister(string $consumerTag): void
    {
        unset($this->consumers[$consumerTag]);
    }

    /**
     * @param callable(non-empty-string): void $cancel
     */
    public function cancelAll(callable $cancel): void
    {
        foreach (array_keys($this->consumers) as $consumerTag) {
            $cancel($consumerTag);
        }
    }
}
