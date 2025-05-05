<?php

declare(strict_types=1);

namespace Thesis\Amqp;

use Amp\DeferredFuture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Thesis\Amqp\Exception\ChannelIsNotTransactional;
use Thesis\Amqp\Exception\ChannelModeIsImpossible;
use Thesis\Amqp\Exception\ChannelWasClosed;
use Thesis\Amqp\Exception\NoAvailableChannel;
use function Amp\async;
use function Amp\delay;

#[CoversClass(Client::class)]
#[CoversClass(Channel::class)]
final class AmqpTest extends TestCase
{
    private Client $client;

    private string $dsn;

    protected function setUp(): void
    {
        parent::setUp();

        $dsn = getenv('THESIS_AMQP_DSN');

        if (!\is_string($dsn) || $dsn === '') {
            self::markTestSkipped('THESIS_AMQP_DSN is not set.');
        }

        $this->dsn = $dsn;
        $this->client = new Client(Config::fromURI($this->dsn));
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->client->disconnect();
    }

    public function testChannelOpenClose(): void
    {
        $channel = $this->client->channel();
        $channel->close();
        self::expectNotToPerformAssertions();
    }

    public function testMultipleChannelOpenClose(): void
    {
        for ($i = 0; $i < 1000; ++$i) {
            $channel = $this->client->channel();
            $channel->close();
        }

        self::expectNotToPerformAssertions();
    }

    /**
     * @param non-empty-string $exchange
     */
    #[TestWith(['events'])]
    public function testDeclareAbsentExchangePassive(string $exchange): void
    {
        $channel = $this->client->channel();
        $channel->exchangeDelete($exchange);

        self::expectException(ChannelWasClosed::class);
        self::expectExceptionMessage("Channel was closed by the server: NOT_FOUND - no exchange '{$exchange}' in vhost '/'.");
        $channel->exchangeDeclare($exchange, passive: true, autoDelete: true);
        $channel->close();
    }

    /**
     * @param non-empty-string $exchange
     */
    #[TestWith(['events'])]
    public function testDeclareExistingExchangePassive(string $exchange): void
    {
        $channel = $this->client->channel();
        $channel->exchangeDelete($exchange);
        $channel->exchangeDeclare($exchange, autoDelete: true);
        $channel->exchangeDeclare($exchange, passive: true, autoDelete: true);
        $channel->close();
        self::expectNotToPerformAssertions();
    }

    /**
     * @param non-empty-string $queue
     */
    #[TestWith(['events.orders'])]
    public function testDeclareAbsentQueuePassive(string $queue): void
    {
        $channel = $this->client->channel();
        $channel->queueDelete($queue);

        self::expectException(ChannelWasClosed::class);
        self::expectExceptionMessage("Channel was closed by the server: NOT_FOUND - no queue '{$queue}' in vhost '/'.");
        $channel->queueDeclare($queue, passive: true, autoDelete: true);
        $channel->close();
    }

    /**
     * @param non-empty-string $queue
     */
    #[TestWith(['events.orders'])]
    public function testDeclareExistingQueuePassive(string $queue): void
    {
        $channel = $this->client->channel();
        $channel->queueDelete($queue);
        $channel->queueDeclare($queue, autoDelete: true);
        $channel->queueDeclare($queue, passive: true, autoDelete: true);
        $channel->close();
        self::expectNotToPerformAssertions();
    }

    /**
     * @param non-empty-string $queue
     */
    #[TestWith(['events.orders'])]
    public function testDeclareQueueTwiceWithDifferentArgumentsPassive(string $queue): void
    {
        $channel = $this->client->channel();
        $channel->queueDelete($queue);
        $channel->queueDeclare($queue, autoDelete: true);
        $channel->queueDeclare($queue, passive: true, autoDelete: true, arguments: [
            'x-queue-type' => 'quorum',
        ]);
        $channel->close();
        self::expectNotToPerformAssertions();
    }

    /**
     * @param non-empty-string $queue
     */
    #[TestWith(['events.orders'])]
    public function testDeclareQueueTwiceWithDifferentArguments(string $queue): void
    {
        $channel = $this->client->channel();
        $channel->queueDelete($queue);
        $channel->queueDeclare($queue, autoDelete: true);

        self::expectException(ChannelWasClosed::class);
        self::expectExceptionMessage("Channel was closed by the server: PRECONDITION_FAILED - inequivalent arg 'x-queue-type' for queue '{$queue}' in vhost '/': received 'quorum' but current is 'classic'.");
        $channel->queueDeclare($queue, autoDelete: true, arguments: [
            'x-queue-type' => 'quorum',
        ]);
        $channel->close();
    }

    /**
     * @param non-empty-string $queue
     */
    #[TestWith(['events.orders'])]
    public function testDeclareQueueTwiceWithDifferentFlags(string $queue): void
    {
        $channel = $this->client->channel();
        $channel->queueDelete($queue);
        $channel->queueDeclare($queue, autoDelete: true);

        self::expectException(ChannelWasClosed::class);
        self::expectExceptionMessage("Channel was closed by the server: PRECONDITION_FAILED - inequivalent arg 'durable' for queue '{$queue}' in vhost '/': received 'true' but current is 'false'.");
        $channel->queueDeclare($queue, durable: true, autoDelete: true);
        $channel->close();
    }

    /**
     * @param non-empty-string $exchange
     * @param non-empty-string $queue
     */
    #[TestWith(['events', 'events.orders', 'orders'])]
    public function testQueueBindToExchange(string $exchange, string $queue, string $routingKey): void
    {
        $channel = $this->client->channel();
        $channel->exchangeDelete($exchange);
        $channel->queueDelete($queue);

        $channel->exchangeDeclare($exchange, autoDelete: true);
        $channel->queueDeclare($queue, autoDelete: true);
        $channel->queueBind($queue, $exchange, $routingKey);
        $channel->close();
        self::expectNotToPerformAssertions();
    }

    #[TestWith([10])]
    #[TestWith([15])]
    #[TestWith([20])]
    public function testChannelsExhausted(int $channelMax): void
    {
        $client = new Client(Config::fromURI("{$this->dsn}?channel_max={$channelMax}"));

        for ($i = 0; $i < $channelMax; ++$i) {
            $client->channel();
        }

        self::expectException(NoAvailableChannel::class);
        self::expectExceptionMessage("You have exceeded the channel limit ({$channelMax}).");
        $client->channel();
        $client->disconnect();
    }

    /**
     * @param non-empty-string $exchange
     * @param non-empty-string $queue
     * @param non-empty-string $routingKey
     * @param non-empty-string $message
     * @param array<string, mixed> $headers
     */
    #[TestWith(['events', 'events.orders', 'orders', 'simple message', ['x' => 'y']])]
    public function testPublishGetAck(string $exchange, string $queue, string $routingKey, string $message, array $headers): void
    {
        $channel = $this->client->channel();
        $channel->exchangeDelete($exchange);
        $channel->queueDelete($queue);

        $channel->exchangeDeclare($exchange, autoDelete: true);
        self::assertSame(0, $channel->queueDeclare($queue, autoDelete: true)->messages);
        $channel->queueBind($queue, $exchange, $routingKey);

        self::assertNull($channel->publish(new Message($message, $headers), $exchange, $routingKey));

        $delivery = $channel->get($queue);
        self::assertNotNull($delivery);
        self::assertSame($message, $delivery->message->body);
        self::assertSame($headers, $delivery->message->headers);
        self::assertSame($exchange, $delivery->exchange);
        self::assertSame($routingKey, $delivery->routingKey);

        $delivery->ack();

        self::assertSame(0, $channel->queueDeclare($queue, passive: true, autoDelete: true)->messages);

        $channel->close();
    }

    /**
     * @param non-empty-string $exchange
     * @param non-empty-string $queue
     * @param non-empty-string $routingKey
     */
    #[TestWith(['events', 'events.orders', 'orders'])]
    public function testPublishBatchGetAck(string $exchange, string $queue, string $routingKey): void
    {
        $channel = $this->client->channel();
        $channel->exchangeDelete($exchange);
        $channel->queueDelete($queue);

        $channel->exchangeDeclare($exchange, autoDelete: true);
        self::assertSame(0, $channel->queueDeclare($queue, autoDelete: true)->messages);
        $channel->queueBind($queue, $exchange, $routingKey);

        $channel->confirmSelect();

        self::assertCount(
            0,
            $channel
                ->publishBatch([
                    new PublishMessage(new Message('1'), exchange: $exchange, routingKey: $routingKey),
                    new PublishMessage(new Message('2'), exchange: $exchange, routingKey: $routingKey),
                    new PublishMessage(new Message('3'), exchange: $exchange, routingKey: $routingKey),
                ])
                ->await()
                ->unconfirmed,
        );

        for ($i = 1; $i <= 3; ++$i) {
            $delivery = $channel->get($queue);
            self::assertNotNull($delivery);
            self::assertSame("{$i}", $delivery->message->body);
            self::assertSame($exchange, $delivery->exchange);
            self::assertSame($routingKey, $delivery->routingKey);

            $delivery->ack();
        }

        self::assertSame(0, $channel->queueDeclare($queue, passive: true, autoDelete: true)->messages);

        $channel->close();
    }

    /**
     * @param non-empty-string $exchange
     * @param non-empty-string $queue
     * @param non-empty-string $routingKey
     * @param non-empty-string $message
     * @param array<string, mixed> $headers
     * @param positive-int $messageCount
     */
    #[TestWith(['events', 'events.orders', 'orders', 'simple message', ['x' => 'y'], 20])]
    public function testAckMultiple(string $exchange, string $queue, string $routingKey, string $message, array $headers, int $messageCount): void
    {
        $channel = $this->client->channel();
        $channel->exchangeDelete($exchange);
        $channel->queueDelete($queue);

        $channel->exchangeDeclare($exchange, autoDelete: true);
        self::assertSame(0, $channel->queueDeclare($queue, autoDelete: true)->messages);
        $channel->queueBind($queue, $exchange, $routingKey);

        for ($i = 0; $i < $messageCount; ++$i) {
            $channel->publish(new Message("{$message}#{$i}", $headers), $exchange, $routingKey);
        }

        $deferred = new DeferredFuture();
        $consumedMessages = 0;

        $channel->qos(prefetchCount: $messageCount);
        $channel->consume(static function (DeliveryMessage $delivery) use (&$consumedMessages, $messageCount, $deferred): void {
            if (++$consumedMessages === $messageCount) {
                $delivery->ack(multiple: true);
                $deferred->complete();
            }
        }, $queue);

        $deferred->getFuture()->await();

        self::assertSame(0, $channel->queuePurge($queue));

        $channel->close();
    }

    /**
     * @param non-empty-string $exchange
     * @param non-empty-string $queue
     * @param non-empty-string $routingKey
     * @param non-empty-string $message
     * @param array<string, mixed> $headers
     * @param positive-int $messageCount
     */
    #[TestWith(['events', 'events.orders', 'orders', 'simple message', ['x' => 'y'], 20])]
    public function testNackMultiple(string $exchange, string $queue, string $routingKey, string $message, array $headers, int $messageCount): void
    {
        $channel = $this->client->channel();
        $channel->exchangeDelete($exchange);
        $channel->queueDelete($queue);

        $channel->exchangeDeclare($exchange);
        self::assertSame(0, $channel->queueDeclare($queue)->messages);
        $channel->queueBind($queue, $exchange, $routingKey);

        for ($i = 0; $i < $messageCount; ++$i) {
            $channel->publish(new Message("{$message}#{$i}", $headers), $exchange, $routingKey);
        }

        $deferred = new DeferredFuture();
        $consumedMessages = 0;

        $channel->qos(prefetchCount: $messageCount * 2);
        $channel->consume(static function (DeliveryMessage $delivery) use (&$consumedMessages, $messageCount, $deferred): void {
            ++$consumedMessages;

            if ($consumedMessages === $messageCount) {
                $delivery->nack(multiple: true);
            } elseif ($consumedMessages === $messageCount * 2) {
                $deferred->complete();
            }
        }, $queue);

        $deferred->getFuture()->await();

        self::assertSame($messageCount * 2, $consumedMessages);

        $channel->close();
    }

    /**
     * @param non-empty-string $exchange
     * @param non-empty-string $queue
     * @param non-empty-string $routingKey
     * @param non-empty-string $message
     * @param array<string, mixed> $headers
     */
    #[TestWith(['events', 'events.orders', 'orders', 'simple message', ['x' => 'y']])]
    public function testPublishGetNack(string $exchange, string $queue, string $routingKey, string $message, array $headers): void
    {
        $channel = $this->client->channel();
        $channel->exchangeDelete($exchange);
        $channel->queueDelete($queue);

        $channel->exchangeDeclare($exchange, autoDelete: true);
        self::assertSame(0, $channel->queueDeclare($queue, autoDelete: true)->messages);
        $channel->queueBind($queue, $exchange, $routingKey);

        self::assertNull($channel->publish(new Message($message, $headers), $exchange, $routingKey));

        $delivery = $channel->get($queue);
        self::assertNotNull($delivery);
        self::assertSame($message, $delivery->message->body);
        self::assertSame($headers, $delivery->message->headers);

        $delivery->nack();
        delay(0.1);

        self::assertSame(1, $channel->queueDeclare($queue, passive: true, autoDelete: true)->messages);

        $channel->close();
    }

    /**
     * @param non-empty-string $exchange
     * @param non-empty-string $queue
     * @param non-empty-string $routingKey
     * @param non-empty-string $message
     * @param array<string, mixed> $headers
     */
    #[TestWith(['events', 'events.orders', 'orders', 'simple message', ['x' => 'y']])]
    public function testPublishGetReject(string $exchange, string $queue, string $routingKey, string $message, array $headers): void
    {
        $channel = $this->client->channel();
        $channel->exchangeDelete($exchange);
        $channel->queueDelete($queue);

        $channel->exchangeDeclare($exchange, autoDelete: true);
        self::assertSame(0, $channel->queueDeclare($queue, autoDelete: true)->messages);
        $channel->queueBind($queue, $exchange, $routingKey);

        self::assertNull($channel->publish(new Message($message, $headers), $exchange, $routingKey));

        $delivery = $channel->get($queue);
        self::assertNotNull($delivery);
        self::assertSame($message, $delivery->message->body);
        self::assertSame($headers, $delivery->message->headers);

        $delivery->reject();
        delay(0.1);

        self::assertSame(1, $channel->queueDeclare($queue, passive: true, autoDelete: true)->messages);

        $channel->close();
    }

    /**
     * @param non-empty-string $exchange
     * @param non-empty-string $queue
     * @param non-empty-string $routingKey
     * @param non-empty-string $message
     * @param array<string, mixed> $headers
     * @param positive-int $messageCount
     */
    #[TestWith(['events', 'events.orders', 'orders', 'simple message', ['x' => 'y'], 20])]
    public function testPublishPurge(string $exchange, string $queue, string $routingKey, string $message, array $headers, int $messageCount): void
    {
        $channel = $this->client->channel();
        $channel->exchangeDelete($exchange);
        $channel->queueDelete($queue);

        $channel->exchangeDeclare($exchange, autoDelete: true);
        self::assertSame(0, $channel->queueDeclare($queue, autoDelete: true)->messages);
        $channel->queueBind($queue, $exchange, $routingKey);

        for ($i = 0; $i < $messageCount; ++$i) {
            self::assertNull($channel->publish(new Message("{$message}#{$i}", $headers), $exchange, $routingKey));
        }

        self::assertSame($messageCount, $channel->queuePurge($queue));

        $channel->close();
    }

    /**
     * @param non-empty-string $queue
     */
    #[TestWith(['test'])]
    public function testIteratorComplete(string $queue): void
    {
        $channel = $this->client->channel();
        $channel->queueDelete($queue);
        $channel->queueDeclare($queue);

        for ($i = 0; $i < 3; ++$i) {
            $channel->publish(new Message("x#{$i}"), routingKey: $queue);
        }

        $consumedMessages = [];

        $channel->qos(prefetchCount: 1);
        $iterator = $channel->consumeIterator($queue, size: 1);
        self::assertSame(1, $channel->queueDeclare($queue, passive: true)->consumers);

        foreach ($iterator as $delivery) {
            $consumedMessages[] = $delivery->message->body;
            $delivery->ack();
            if (\count($consumedMessages) === 3) {
                $iterator->complete();
            }
        }

        self::assertSame(['x#0', 'x#1', 'x#2'], $consumedMessages);
        self::assertSame(0, $channel->queueDeclare($queue, passive: true)->consumers);

        $channel->close();
    }

    /**
     * @param non-empty-string $queue
     */
    #[TestWith(['test'])]
    public function testIteratorCancel(string $queue): void
    {
        $channel = $this->client->channel();
        $channel->queueDelete($queue);
        $channel->queueDeclare($queue);

        for ($i = 0; $i < 3; ++$i) {
            $channel->publish(new Message("x#{$i}"), routingKey: $queue);
        }

        $consumedMessages = [];

        $channel->qos(prefetchCount: 1);
        $iterator = $channel->consumeIterator($queue, size: 1);
        self::assertSame(1, $channel->queueDeclare($queue, passive: true)->consumers);

        self::expectException(\Exception::class);
        self::expectExceptionMessage('cancel iterator');
        foreach ($iterator as $delivery) {
            $consumedMessages[] = $delivery->message->body;
            $delivery->ack();
            if (\count($consumedMessages) === 3) {
                $iterator->cancel(new \Exception('cancel iterator'));
            }
        }

        self::assertSame(['x#0', 'x#1', 'x#2'], $consumedMessages);
        self::assertSame(0, $channel->queueDeclare($queue, passive: true)->consumers);

        $channel->close();
    }

    /**
     * @param non-empty-string $exchange
     * @param non-empty-string $queue
     * @param non-empty-string $routingKey
     * @param non-empty-string $message
     * @param array<string, mixed> $headers
     * @param positive-int $messageCount
     */
    #[TestWith(['events', 'events.orders', 'orders', 'simple message', ['x' => 'y'], 20])]
    public function testPublishConsume(string $exchange, string $queue, string $routingKey, string $message, array $headers, int $messageCount): void
    {
        $channel = $this->client->channel();
        $channel->exchangeDelete($exchange);
        $channel->queueDelete($queue);

        $channel->exchangeDeclare($exchange, autoDelete: true);
        self::assertSame(0, $channel->queueDeclare($queue, autoDelete: true)->messages);
        $channel->queueBind($queue, $exchange, $routingKey);

        $publishedMessages = [];
        for ($i = 0; $i < $messageCount; ++$i) {
            $publishedMessages[$exchange][] = $messageBody = "{$message}#{$i}";
            $channel->publish(new Message($messageBody, $headers), $exchange, $routingKey);
        }

        $deferred = new DeferredFuture();
        $consumedMessages = [];

        $channel->qos(prefetchCount: $messageCount);
        $channel->consume(static function (DeliveryMessage $delivery) use (&$consumedMessages, $messageCount, $deferred): void {
            $consumedMessages[$delivery->exchange][] = $delivery->message->body;
            $delivery->ack();
            if (\count($consumedMessages[$delivery->exchange]) === $messageCount) {
                $deferred->complete($consumedMessages);
            }
        }, $queue);

        self::assertSame($publishedMessages, $deferred->getFuture()->await());
        self::assertSame(0, $channel->queuePurge($queue));

        $channel->close();
    }

    public function testPublishConsumeBatch(): void
    {
        $channel = $this->client->channel();

        $queue = $channel->queueDeclare();

        for ($i = 0; $i < 8; ++$i) {
            $channel->publish(new Message("{$i}"), routingKey: $queue->name);
        }

        /** @var list<list<string>> $messages */
        $messages = [];

        $consumerTag = $channel->consumeBatch(
            static function (ConsumeBatch $batch) use (&$messages): void {
                $messages[] = array_map(static fn(DeliveryMessage $delivery): string => $delivery->message->body, $batch->deliveries);
                $batch->ack();
            },
            count: 5,
            timeout: 0.1,
            queue: $queue->name,
        );

        delay(0.3);

        $channel->cancel($consumerTag);

        self::assertSame(
            [['0', '1', '2', '3', '4'], ['5', '6', '7']],
            $messages,
        );
        self::assertSame(0, $channel->queueDelete($queue->name));

        $channel->close();
    }

    public function testPublishConsumeBatchIterator(): void
    {
        $channel = $this->client->channel();

        $queue = $channel->queueDeclare();
        self::assertSame(0, $queue->messages);

        for ($i = 0; $i < 8; ++$i) {
            $channel->publish(new Message("{$i}"), routingKey: $queue->name);
        }

        $iterator = $channel->consumeBatchIterator(
            count: 5,
            timeout: 0.1,
            queue: $queue->name,
        );

        $future = async(static function () use ($iterator): void {
            delay(0.3);
            $iterator->complete();
        });

        /** @var list<list<string>> $messages */
        $messages = [];

        foreach ($iterator as $batch) {
            $messages[] = array_map(static fn(DeliveryMessage $delivery): string => $delivery->message->body, $batch->deliveries);
            $batch->ack();
        }

        $future->await();

        self::assertSame(
            [['0', '1', '2', '3', '4'], ['5', '6', '7']],
            $messages,
        );
        self::assertSame(0, $channel->queueDelete($queue->name));

        $channel->close();
    }

    /**
     * @param non-empty-string $queue
     */
    #[TestWith(['test'])]
    public function testCancelConsumer(string $queue): void
    {
        $channel = $this->client->channel();
        $channel->queueDelete($queue);

        self::assertSame(0, $channel->queueDeclare($queue)->consumers);

        $channel->qos(prefetchCount: 1);
        $consumerTag = $channel->consume(static function (): void {}, $queue);

        self::assertSame(1, $channel->queueDeclare($queue, passive: true)->consumers);

        $channel->cancel($consumerTag);
        self::assertSame(0, $channel->queueDeclare($queue, passive: true)->consumers);

        $channel->close();
    }

    /**
     * @param non-empty-string $exchange
     * @param non-empty-string $queue
     * @param non-empty-string $routingKey
     */
    #[TestWith(['events', 'events.orders', 'orders'])]
    public function testPublishConfirm(string $exchange, string $queue, string $routingKey): void
    {
        $channel = $this->client->channel();
        $channel->exchangeDelete($exchange);
        $channel->queueDelete($queue);

        $channel->exchangeDeclare($exchange, autoDelete: true);
        self::assertSame(0, $channel->queueDeclare($queue, autoDelete: true)->messages);
        $channel->queueBind($queue, $exchange, $routingKey);

        $channel->confirmSelect();

        $confirmation = $channel->publish(new Message('x'), $exchange, $routingKey);
        self::assertNotNull($confirmation);
        self::assertSame(PublishResult::Acked, $confirmation->await());

        $channel->close();
    }

    public function testPublishReturn(): void
    {
        $channel = $this->client->channel();

        /** @var DeferredFuture<DeliveryMessage> $deferred */
        $deferred = new DeferredFuture();
        $channel->onReturn($deferred->complete(...));

        $channel->publish(new Message('x'), routingKey: 'not_exists', mandatory: true);

        $delivery = $deferred->getFuture()->await();
        self::assertSame('x', $delivery->message->body);
        self::assertSame('not_exists', $delivery->routingKey);

        $channel->close();
    }

    public function testPublishExplicitReturnWithoutMandatory(): void
    {
        $channel = $this->client->channel();
        $channel->confirmSelect();

        $confirmation = $channel->publish(new Message('x'), routingKey: 'not_exists');

        self::assertSame(PublishResult::Acked, $confirmation?->await());

        $channel->close();
    }

    public function testPublishExplicitReturn(): void
    {
        $channel = $this->client->channel();
        $channel->confirmSelect();

        $returns = 0;

        // Callbacks for deliveries with X-Thesis-Mandatory-Id will not be call.
        $channel->onReturn(static function () use (&$returns): void {
            ++$returns;
        });

        $confirmation = $channel->publish(new Message('x'), routingKey: 'not_exists', mandatory: true);

        self::assertSame(PublishResult::Unrouted, $confirmation?->await());
        self::assertSame(0, $returns);

        $channel->close();
    }

    public function testPublishBatchExplicitReturn(): void
    {
        $channel = $this->client->channel();
        $channel->confirmSelect();

        $returns = 0;

        // Callbacks for deliveries with X-Thesis-Mandatory-Id will not be call.
        $channel->onReturn(static function () use (&$returns): void {
            ++$returns;
        });

        $confirmation = $channel->publishBatch([
            new PublishMessage(new Message('x'), routingKey: 'not_exists', mandatory: true),
            new PublishMessage(new Message('y'), routingKey: 'not_exists', mandatory: true),
            new PublishMessage(new Message('z'), routingKey: 'not_exists', mandatory: true),
        ]);

        $result = $confirmation->await();

        self::assertCount(3, $result->unrouted);
        self::assertCount(0, $result->unconfirmed);
        self::assertSame(0, $returns);

        $channel->close();
    }

    /**
     * @param non-empty-string $queue
     * @param ?int<0, 9> $priority
     * @param array<string, mixed> $headers
     */
    #[TestWith([
        'events.orders',
        'application/json',
        'json',
        DeliveryMode::Transient,
        1,
        '5a2da3f7-e2d4-4029-92f7-d821971b85a3',
        '60000',
        '1c082d18-066f-4bb4-8766-f6fb712fbe23',
        new \DateTimeImmutable('@1735883627'),
        'orders',
        'thesis',
        'demo',
        ['x' => 'y'],
    ])]
    public function testMessageProperties(
        string $queue,
        ?string $contentType = null,
        ?string $contentEncoding = null,
        DeliveryMode $deliveryMode = DeliveryMode::Whatever,
        ?int $priority = null,
        ?string $correlationId = null,
        ?string $expiration = null,
        ?string $messageId = null,
        ?\DateTimeImmutable $timestamp = null,
        ?string $type = null,
        ?string $userId = null,
        ?string $appId = null,
        array $headers = [],
    ): void {
        $channel = $this->client->channel();
        $channel->queueDelete($queue);
        $channel->queueDeclare($queue, autoDelete: true);
        $message = new Message(
            'x',
            headers: $headers,
            contentType: $contentType,
            contentEncoding: $contentEncoding,
            deliveryMode: $deliveryMode,
            priority: $priority,
            correlationId: $correlationId,
            expiration: $expiration,
            messageId: $messageId,
            timestamp: $timestamp,
            type: $type,
            userId: $userId,
            appId: $appId,
        );

        $channel->publish($message, routingKey: $queue);

        $delivery = $channel->get($queue);
        self::assertNotNull($delivery);
        $delivery->ack();
        self::assertEquals($message, $delivery->message);

        $channel->close();
    }

    /**
     * @param non-empty-string $queue
     * @param positive-int $messageSize
     */
    #[TestWith(['events.orders', 100000])]
    public function testBigMessage(string $queue, int $messageSize): void
    {
        $channel = $this->client->channel();
        $channel->queueDelete($queue);
        $channel->queueDeclare($queue, autoDelete: true);

        $channel->publish(new Message(str_repeat('x', $messageSize)), routingKey: $queue);

        $delivery = $channel->get($queue);
        self::assertSame($messageSize, \strlen($delivery->message->body ?? ''));

        $delivery?->ack();

        $channel->close();
    }

    /**
     * @param non-empty-string $queue
     */
    #[TestWith(['events.orders'])]
    public function testEmptyMessage(string $queue): void
    {
        $channel = $this->client->channel();
        $channel->queueDelete($queue);
        $channel->queueDeclare($queue, autoDelete: true);

        $channel->publish(new Message(''), routingKey: $queue);

        $delivery = $channel->get($queue, noAck: true);
        self::assertNotNull($delivery);
        self::assertEmpty($delivery->message->body);

        $channel->close();
    }

    /**
     * @param non-empty-string $queue
     */
    #[TestWith(['events.orders'])]
    public function testTx(string $queue): void
    {
        $channel = $this->client->channel();
        $channel->queueDelete($queue);
        $channel->queueDeclare($queue, autoDelete: true);

        $channel->txSelect();

        $channel->publish(new Message('x'), routingKey: $queue);
        $channel->txCommit();

        $delivery = $channel->get($queue, true);
        self::assertSame('x', $delivery?->message->body);

        $channel->publish(new Message('xx'), routingKey: $queue);
        $channel->txRollback();

        $delivery = $channel->get($queue, true);
        self::assertNull($delivery);

        $channel->close();
    }

    /**
     * @param non-empty-string $queue
     */
    #[TestWith(['events.orders'])]
    public function testTransactional(string $queue): void
    {
        $channel = $this->client->channel();
        $channel->queueDelete($queue);
        $channel->queueDeclare($queue, autoDelete: true);

        $channel->transactional(static function (Channel $channel) use ($queue): void {
            $channel->publish(new Message('x'), routingKey: $queue);
        });

        $delivery = $channel->get($queue, true);
        self::assertSame('x', $delivery?->message->body);

        self::expectException(\Exception::class);
        self::expectExceptionMessage('rollback');
        $channel->transactional(static function (Channel $channel) use ($queue): void {
            $channel->publish(new Message('x'), routingKey: $queue);

            throw new \Exception('rollback');
        });

        $delivery = $channel->get($queue, true);
        self::assertNull($delivery);

        $channel->close();
    }

    /**
     * @param non-empty-string $queue
     */
    #[TestWith(['events.orders'])]
    public function testTxTwice(string $queue): void
    {
        $channel = $this->client->channel();
        $channel->queueDelete($queue);
        $channel->queueDeclare($queue, autoDelete: true);

        $channel->txSelect();

        self::expectException(ChannelModeIsImpossible::class);
        self::expectExceptionMessage('Channel 1 is already transactional.');
        $channel->txSelect();

        $channel->close();
    }

    public function testTxCommitCannotBeCallOnNonTransactionChannel(): void
    {
        $channel = $this->client->channel();

        self::expectException(ChannelIsNotTransactional::class);
        $channel->txCommit();

        $channel->close();
    }

    public function testTxRollbackCannotBeCallOnNonTransactionChannel(): void
    {
        $channel = $this->client->channel();

        self::expectException(ChannelIsNotTransactional::class);
        $channel->txRollback();

        $channel->close();
    }

    /**
     * @param non-empty-string $queue
     */
    #[TestWith(['events.orders'])]
    public function testTxOnConfirm(string $queue): void
    {
        $channel = $this->client->channel();
        $channel->queueDelete($queue);
        $channel->queueDeclare($queue, autoDelete: true);

        $channel->confirmSelect();

        self::expectException(ChannelModeIsImpossible::class);
        self::expectExceptionMessage('Cannot put confirming channel 1 in transactional mode.');
        $channel->txSelect();

        $channel->close();
    }

    /**
     * @param non-empty-string $queue
     */
    #[TestWith(['events.orders'])]
    public function testConfirmOnTx(string $queue): void
    {
        $channel = $this->client->channel();
        $channel->queueDelete($queue);
        $channel->queueDeclare($queue, autoDelete: true);

        $channel->txSelect();

        self::expectException(ChannelModeIsImpossible::class);
        self::expectExceptionMessage('Cannot put transactional channel 1 in confirming mode.');
        $channel->confirmSelect();

        $channel->close();
    }

    /**
     * @param non-empty-string $queue
     */
    #[TestWith(['events.orders'])]
    public function testRecover(string $queue): void
    {
        $channel = $this->client->channel();
        $channel->queueDelete($queue);
        $channel->queueDeclare($queue, autoDelete: true);

        $channel->publish(new Message('x'), routingKey: $queue);

        $delivery = $channel->get($queue);
        self::assertNotNull($delivery);
        self::assertSame('x', $delivery->message->body);
        self::assertFalse($delivery->redelivered);

        $channel->recover(true);

        $delivery = $channel->get($queue);
        self::assertNotNull($delivery);
        self::assertSame('x', $delivery->message->body);
        self::assertTrue($delivery->redelivered);
        $delivery->ack();

        $channel->close();
    }
}
