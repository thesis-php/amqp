<?php

declare(strict_types=1);

namespace Thesis\Amqp\Benchmark;

use Amp\DeferredFuture;
use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Revs;
use Thesis\Amqp\Channel;
use Thesis\Amqp\Client;
use Thesis\Amqp\Config;
use Thesis\Amqp\DeliveryMessage;
use Thesis\Amqp\Message;

#[BeforeMethods('setUp')]
#[AfterMethods('tearDown')]
final class ChannelBench
{
    private Client $client;

    private Channel $channel;

    /** @var non-empty-string */
    private string $queue = 'thesis.bench.queue';

    private Message $message;

    public function setUp(): void
    {
        $this->message = new Message(bin2hex(random_bytes(1024)));
        $this->client = new Client(Config::default());
        $this->channel = $this->client->channel();
        $this->channel->queueDeclare(queue: $this->queue, autoDelete: true);
    }

    public function tearDown(): void
    {
        $this->client->disconnect();
    }

    #[Revs(1)]
    public function benchPublish(): void
    {
        for ($i = 0; $i < 100_000; ++$i) {
            $this->channel->publish($this->message, routingKey: $this->queue);
        }

        $this->channel->publish(new Message('quit'), routingKey: $this->queue);
    }

    #[Revs(1)]
    public function benchConsume(): void
    {
        /** @var DeferredFuture<void> $deferred */
        $deferred = new DeferredFuture();

        $consumerTag = $this->channel->consume(
            callback: static function (DeliveryMessage $delivery) use ($deferred): void {
                if ($delivery->message->body === 'quit') {
                    $deferred->complete();
                }
            },
            queue: $this->queue,
            noAck: true,
        );

        $deferred->getFuture()->await();
        $this->channel->cancel($consumerTag);
    }
}
