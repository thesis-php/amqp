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

    public function __construct(
        DeliverySupervisor $supervisor,
        Channel $channel,
    ) {
        $consumers = &$this->consumers;

        $supervisor->addConsumeListener(static function (DeliveryMessage $delivery) use (&$consumers, $channel): void {
            $consumer = $consumers[$delivery->consumerTag] ?? null;
            if ($consumer !== null) {
                $consumer($delivery, $channel);
            }
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
}
