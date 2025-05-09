<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Delivery;

use Thesis\Amqp\Channel;
use Thesis\Amqp\DeliveryMessage;

/**
 * @internal
 * @phpstan-type Listener = callable(DeliveryMessage, Channel): void
 * @template-implements \IteratorAggregate<non-empty-string, Listener>
 */
final class Consumer implements \IteratorAggregate
{
    public static function create(DeliverySupervisor $supervisor, Channel $channel): self
    {
        $consumer = new self($supervisor, $channel);
        $consumer->run();

        return $consumer;
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

    public function getIterator(): \Traversable
    {
        yield from $this->consumers;
    }

    /** @var array<non-empty-string, Listener> */
    private array $consumers = [];

    private function __construct(
        private readonly DeliverySupervisor $supervisor,
        private readonly Channel $channel,
    ) {}

    private function run(): void
    {
        $consumers = &$this->consumers;
        $channel = $this->channel;

        $this->supervisor->addConsumeListener(static function (DeliveryMessage $delivery) use (&$consumers, $channel): void {
            $consumer = $consumers[$delivery->consumerTag] ?? null;
            if ($consumer !== null) {
                $consumer($delivery, $channel);
            }
        });

        $this->supervisor->addShutdownListener(static function () use (&$consumers): void {
            $consumers = [];
        });
    }
}
