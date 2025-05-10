<?php

declare(strict_types=1);

namespace Thesis\Amqp;

use Amp\Cancellation;
use Amp\NullCancellation;
use Revolt\EventLoop;
use Thesis\Amqp\Internal\AtomicGet;
use Thesis\Amqp\Internal\Batch\BatchConsumer;
use Thesis\Amqp\Internal\Batch\ConsumeBatchOptions;
use Thesis\Amqp\Internal\Cancellation\CancellationStorage;
use Thesis\Amqp\Internal\Cancellation\Canceller;
use Thesis\Amqp\Internal\ChannelMode;
use Thesis\Amqp\Internal\ConfirmationListener;
use Thesis\Amqp\Internal\Delivery\Consumer;
use Thesis\Amqp\Internal\Delivery\ConsumerTagGenerator;
use Thesis\Amqp\Internal\Delivery\DeliverySupervisor;
use Thesis\Amqp\Internal\Delivery\Receiver;
use Thesis\Amqp\Internal\Hooks;
use Thesis\Amqp\Internal\Io\AmqpConnection;
use Thesis\Amqp\Internal\MessageProperties;
use Thesis\Amqp\Internal\Properties;
use Thesis\Amqp\Internal\Protocol;
use Thesis\Amqp\Internal\Protocol\Frame;
use Thesis\Amqp\Internal\Returns;
use Thesis\Sync;
use function Amp\weakClosure;

/**
 * @api
 * @phpstan-import-type ReturnCallback from Returns\ReturnListener
 */
final class Channel
{
    private readonly Returns\ReturnListener $returns;

    private readonly Returns\FutureBoundedReturnListener $boundedReturns;

    private readonly DeliverySupervisor $supervisor;

    private readonly Consumer $consumer;

    private readonly ConsumerTagGenerator $consumerTags;

    private readonly ConfirmationListener $confirms;

    private readonly CancellationStorage $cancellations;

    private readonly AtomicGet $get;

    private ChannelMode $mode = ChannelMode::Regular;

    /** @var ?Sync\Once<bool> */
    private ?Sync\Once $closed = null;

    /**
     * @param non-negative-int $channelId
     */
    public function __construct(
        private readonly int $channelId,
        private readonly AmqpConnection $connection,
        private readonly Properties $properties,
        private readonly Hooks $hooks,
    ) {
        $this->supervisor = new DeliverySupervisor($this, $this->hooks, $this->channelId);
        $this->consumerTags = new ConsumerTagGenerator();
        $this->consumer = new Consumer($this->supervisor);
        $this->get = new AtomicGet(new Receiver($this->supervisor), $this->connection, $this->channelId);
        $this->returns = new Returns\ReturnListener($this->supervisor);
        $this->boundedReturns = new Returns\FutureBoundedReturnListener($this->supervisor);
        $this->confirms = new ConfirmationListener($this->hooks, $this->channelId);
        $this->cancellations = new CancellationStorage();

        $this->supervisor->run();
    }

    /**
     * @throws \Throwable
     */
    public function publish(
        Message $message,
        string $exchange = '',
        string $routingKey = '',
        bool $mandatory = false,
        bool $immediate = false,
    ): ?PublishConfirmation {
        /** @var ?PublishConfirmation $confirmation */
        $confirmation = null;

        if ($this->mode === ChannelMode::Confirm) {
            if ($mandatory) {
                [$message, $returnFuture] = $this->boundedReturns->trace($message);
            }

            $confirmation = $this->confirms->newConfirmation($returnFuture ?? null);
        }

        $this->connection->writeFrame(
            $this->doPublish($message, $exchange, $routingKey, $mandatory, $immediate),
        );

        return $confirmation;
    }

    /**
     * @param non-empty-list<PublishMessage> $publishMessages
     * @throws \Throwable
     */
    public function publishBatch(array $publishMessages): PublishBatchConfirmation
    {
        /** @var list<PublishConfirmation> $confirmations */
        $confirmations = [];

        /** @var array<non-negative-int, PublishMessage> $messages */
        $messages = [];

        $this->connection->writeFrame((function () use ($publishMessages, &$confirmations, &$messages): \Generator {
            foreach ($publishMessages as $publishMessage) {
                if ($this->mode === ChannelMode::Confirm) {
                    if ($publishMessage->mandatory) {
                        [$message, $returnFuture] = $this->boundedReturns->trace($publishMessage->message);

                        $publishMessage = new PublishMessage(
                            message: $message,
                            exchange: $publishMessage->exchange,
                            routingKey: $publishMessage->routingKey,
                            mandatory: $publishMessage->mandatory,
                            immediate: $publishMessage->immediate,
                        );
                    }

                    $confirmation = $this->confirms->newConfirmation($returnFuture ?? null);

                    $confirmations[] = $confirmation;
                    $messages[$confirmation->deliveryTag] = $publishMessage;
                }

                yield from $this->doPublish(
                    $publishMessage->message,
                    $publishMessage->exchange,
                    $publishMessage->routingKey,
                    $publishMessage->mandatory,
                    $publishMessage->immediate,
                );
            }
        })());

        return new PublishBatchConfirmation($messages, $confirmations);
    }

    /**
     * @throws \Throwable
     */
    public function get(string $queue = '', bool $noAck = false, ?Cancellation $cancellation = null): ?DeliveryMessage
    {
        return $this->get->receive($queue, $noAck, $cancellation);
    }

    /**
     * @throws \Throwable
     */
    public function ack(DeliveryMessage $delivery, bool $multiple = false): void
    {
        $this->connection->writeFrame(Protocol\Method::basicAck(
            channelId: $this->channelId,
            deliveryTag: $delivery->deliveryTag,
            multiple: $multiple,
        ));
    }

    /**
     * @throws \Throwable
     */
    public function nack(DeliveryMessage $delivery, bool $multiple = false, bool $requeue = true): void
    {
        $this->connection->writeFrame(Protocol\Method::basicNack(
            channelId: $this->channelId,
            deliveryTag: $delivery->deliveryTag,
            multiple: $multiple,
            requeue: $requeue,
        ));
    }

    /**
     * @throws \Throwable
     */
    public function reject(DeliveryMessage $delivery, bool $requeue = true): void
    {
        $this->connection->writeFrame(Protocol\Method::basicReject(
            channelId: $this->channelId,
            deliveryTag: $delivery->deliveryTag,
            requeue: $requeue,
        ));
    }

    /**
     * @throws \Throwable
     */
    public function recover(bool $requeue = false): void
    {
        $this->connection->writeFrame(Protocol\Method::basicRecover(
            channelId: $this->channelId,
            requeue: $requeue,
        ));

        $this->await(Frame\BasicRecoverOk::class);
    }

    /**
     * @param non-negative-int $prefetchSize
     * @param non-negative-int $prefetchCount
     * @throws \Throwable
     */
    public function qos(int $prefetchSize = 0, int $prefetchCount = 0, bool $global = false): void
    {
        $this->connection->writeFrame(Protocol\Method::basicQos(
            channelId: $this->channelId,
            prefetchSize: $prefetchSize,
            prefetchCount: $prefetchCount,
            global: $global,
        ));

        $this->await(Frame\BasicQosOk::class);
    }

    /**
     * @param callable(DeliveryMessage, self): void $callback
     * @param array<string, mixed> $arguments
     * @return non-empty-string Consumer tag
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
    ): string {
        $consumerTag = $this->consumerTags->select($consumerTag);

        $this->consumer->register($consumerTag, $callback);

        $this->connection->writeFrame(Protocol\Method::basicConsume(
            channelId: $this->channelId,
            queue: $queue,
            consumerTag: $consumerTag,
            noLocal: $noLocal,
            noAck: $noAck,
            exclusive: $exclusive,
            noWait: $noWait,
            arguments: $arguments,
        ));

        if (!$noWait) {
            $this->await(Frame\BasicConsumeOk::class);
        }

        return $consumerTag;
    }

    /**
     * @param array<string, mixed> $arguments
     * @param non-negative-int $size should be equal to prefetch count on qos method
     * @return Iterator<DeliveryMessage>
     * @throws \Throwable
     */
    public function consumeIterator(
        string $queue = '',
        string $consumerTag = '',
        int $size = 0,
        bool $noLocal = false,
        bool $noAck = false,
        bool $exclusive = false,
        bool $noWait = false,
        array $arguments = [],
    ): Iterator {
        $consumerTag = $this->consumerTags->select($consumerTag);

        /** @var Internal\QueueIterator<DeliveryMessage> $iterator */
        $iterator = Internal\QueueIterator::buffered($consumerTag, $this, $size);

        $this->consume(
            callback: $iterator->push(...),
            queue: $queue,
            consumerTag: $consumerTag,
            noLocal: $noLocal,
            noAck: $noAck,
            exclusive: $exclusive,
            noWait: $noWait,
            arguments: $arguments,
        );

        return $iterator;
    }

    /**
     * @param callable(ConsumeBatch, self): void $callback
     * @param positive-int $count
     * @param ?float $timeout in seconds
     * @param array<string, mixed> $arguments
     * @return non-empty-string Consumer tag
     * @throws \Throwable
     */
    public function consumeBatch(
        callable $callback,
        int $count,
        ?float $timeout = null,
        string $queue = '',
        string $consumerTag = '',
        bool $noLocal = false,
        bool $noAck = false,
        bool $exclusive = false,
        bool $noWait = false,
        bool $global = false,
        array $arguments = [],
    ): string {
        $this->qos(
            prefetchCount: $count,
            global: $global,
        );

        $consumerTag = $this->consumerTags->select($consumerTag);

        $iterator = $this->consumeIterator(
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

        $this->cancellations->add($consumerTag, $canceller);

        $consumer = new BatchConsumer(
            $this,
            new ConsumeBatchOptions($count, $timeout),
            $canceller->cancellation(),
        );

        EventLoop::queue(
            $consumer->consume(...),
            $iterator,
            $callback,
        );

        return $consumerTag;
    }

    /**
     * @param positive-int $count
     * @param ?float $timeout in seconds
     * @param array<string, mixed> $arguments
     * @return Iterator<ConsumeBatch>
     * @throws \Throwable
     */
    public function consumeBatchIterator(
        int $count,
        ?float $timeout = null,
        string $queue = '',
        string $consumerTag = '',
        bool $noLocal = false,
        bool $noAck = false,
        bool $exclusive = false,
        bool $noWait = false,
        bool $global = false,
        array $arguments = [],
    ): Iterator {
        $consumerTag = $this->consumerTags->select($consumerTag);

        /** @var Internal\QueueIterator<ConsumeBatch> $iterator */
        $iterator = Internal\QueueIterator::buffered($consumerTag, $this, $count);

        $this->consumeBatch(
            callback: $iterator->push(...),
            count: $count,
            timeout: $timeout,
            queue: $queue,
            consumerTag: $consumerTag,
            noLocal: $noLocal,
            noAck: $noAck,
            exclusive: $exclusive,
            noWait: $noWait,
            global: $global,
            arguments: $arguments,
        );

        return $iterator;
    }

    /**
     * @param non-empty-string $consumerTag
     * @throws \Throwable
     */
    public function cancel(string $consumerTag, bool $noWait = false): void
    {
        $this->connection->writeFrame(Protocol\Method::basicCancel(
            channelId: $this->channelId,
            consumerTag: $consumerTag,
            noWait: $noWait,
        ));

        if (!$noWait) {
            $this->await(Frame\BasicCancelOk::class);
        }

        $this->consumer->unregister($consumerTag);
        $this->cancellations->cancel($consumerTag, $noWait);
    }

    /**
     * @param non-empty-string $exchange
     * @param non-empty-string $exchangeType
     * @param array<string, mixed> $arguments
     * @throws \Throwable
     */
    public function exchangeDeclare(
        string $exchange,
        string $exchangeType = 'direct',
        bool $passive = false,
        bool $durable = false,
        bool $autoDelete = false,
        bool $internal = false,
        bool $noWait = false,
        array $arguments = [],
    ): void {
        $this->connection->writeFrame(Protocol\Method::exchangeDeclare(
            channelId: $this->channelId,
            exchange: $exchange,
            exchangeType: $exchangeType,
            passive: $passive,
            durable: $durable,
            autoDelete: $autoDelete,
            internal: $internal,
            noWait: $noWait,
            arguments: $arguments,
        ));

        if (!$noWait) {
            $this->await(Frame\ExchangeDeclareOk::class);
        }
    }

    /**
     * @param non-empty-string $destination
     * @param non-empty-string $source
     * @param array<string, mixed> $arguments
     * @throws \Throwable
     */
    public function exchangeBind(
        string $destination,
        string $source,
        string $routingKey = '',
        array $arguments = [],
        bool $noWait = false,
    ): void {
        $this->connection->writeFrame(Protocol\Method::exchangeBind(
            channelId: $this->channelId,
            destination: $destination,
            source: $source,
            routingKey: $routingKey,
            arguments: $arguments,
            noWait: $noWait,
        ));

        if (!$noWait) {
            $this->await(Frame\ExchangeBindOk::class);
        }
    }

    /**
     * @param non-empty-string $destination
     * @param non-empty-string $source
     * @param array<string, mixed> $arguments
     * @throws \Throwable
     */
    public function exchangeUnbind(
        string $destination,
        string $source,
        string $routingKey = '',
        array $arguments = [],
        bool $noWait = false,
    ): void {
        $this->connection->writeFrame(Protocol\Method::exchangeUnbind(
            channelId: $this->channelId,
            destination: $destination,
            source: $source,
            routingKey: $routingKey,
            arguments: $arguments,
            noWait: $noWait,
        ));

        if (!$noWait) {
            $this->await(Frame\ExchangeUnbindOk::class);
        }
    }

    /**
     * @param non-empty-string $exchange
     * @throws \Throwable
     */
    public function exchangeDelete(
        string $exchange,
        bool $ifUnused = false,
        bool $noWait = false,
    ): void {
        $this->connection->writeFrame(Protocol\Method::exchangeDelete(
            channelId: $this->channelId,
            exchange: $exchange,
            ifUnused: $ifUnused,
            noWait: $noWait,
        ));

        if (!$noWait) {
            $this->await(Frame\ExchangeDeleteOk::class);
        }
    }

    /**
     * @param array<string, mixed> $arguments
     * @return ($noWait is true ? null : Queue)
     * @throws \Throwable
     */
    public function queueDeclare(
        string $queue = '',
        bool $passive = false,
        bool $durable = false,
        bool $exclusive = false,
        bool $autoDelete = false,
        bool $noWait = false,
        array $arguments = [],
    ): ?Queue {
        $this->connection->writeFrame(Protocol\Method::queueDeclare(
            channelId: $this->channelId,
            queue: $queue,
            passive: $passive,
            durable: $durable,
            exclusive: $exclusive,
            autoDelete: $autoDelete,
            noWait: $noWait,
            arguments: $arguments,
        ));

        if ($noWait) {
            return null;
        }

        $frame = $this->await(Frame\QueueDeclareOk::class);

        return new Queue($frame->queue, $frame->messages, $frame->consumers);
    }

    /**
     * @param array<string, mixed> $arguments
     * @throws \Throwable
     */
    public function queueBind(
        string $queue = '',
        string $exchange = '',
        string $routingKey = '',
        bool $noWait = false,
        array $arguments = [],
    ): void {
        $this->connection->writeFrame(Protocol\Method::queueBind(
            channelId: $this->channelId,
            queue: $queue,
            exchange: $exchange,
            routingKey: $routingKey,
            arguments: $arguments,
            noWait: $noWait,
        ));

        if (!$noWait) {
            $this->await(Frame\QueueBindOk::class);
        }
    }

    /**
     * @param non-empty-string $queue
     * @param array<string, mixed> $arguments
     * @throws \Throwable
     */
    public function queueUnbind(
        string $queue,
        string $exchange = '',
        string $routingKey = '',
        array $arguments = [],
    ): void {
        $this->connection->writeFrame(Protocol\Method::queueUnbind(
            channelId: $this->channelId,
            queue: $queue,
            exchange: $exchange,
            routingKey: $routingKey,
            arguments: $arguments,
        ));

        $this->await(Frame\QueueUnbindOk::class);
    }

    /**
     * @param non-empty-string $queue
     * @return ($noWait is true ? null : non-negative-int)
     * @throws \Throwable
     */
    public function queuePurge(string $queue, bool $noWait = false): ?int
    {
        $this->connection->writeFrame(Protocol\Method::queuePurge(
            channelId: $this->channelId,
            queue: $queue,
            noWait: $noWait,
        ));

        if ($noWait) {
            return null;
        }

        return $this->await(Frame\QueuePurgeOk::class)->messages;
    }

    /**
     * @param non-empty-string $queue
     * @return ($noWait is true ? null : non-negative-int)
     * @throws \Throwable
     */
    public function queueDelete(
        string $queue,
        bool $ifUnused = false,
        bool $ifEmpty = false,
        bool $noWait = false,
    ): ?int {
        $this->connection->writeFrame(Protocol\Method::queueDelete(
            channelId: $this->channelId,
            queue: $queue,
            ifUnused: $ifUnused,
            ifEmpty: $ifEmpty,
            noWait: $noWait,
        ));

        if ($noWait) {
            return null;
        }

        return $this->await(Frame\QueueDeleteOk::class)->messages;
    }

    /**
     * @param callable(self): void $tx
     * @throws \Throwable
     */
    public function transactional(callable $tx): void
    {
        if (!$this->mode->transactional()) {
            $this->txSelect();
        }

        try {
            $tx($this);
        } catch (\Throwable $e) {
            $this->txRollback();

            throw $e;
        }

        $this->txCommit();
    }

    /**
     * @throws \Throwable
     */
    public function txSelect(): void
    {
        if ($this->mode->confirming()) {
            throw Exception\ChannelModeIsImpossible::inConfirmation($this->channelId);
        }

        if ($this->mode->transactional()) {
            throw Exception\ChannelModeIsImpossible::alreadyTransactional($this->channelId);
        }

        $this->connection->writeFrame(Protocol\Method::txSelect($this->channelId));

        $this->await(Frame\TxSelectOk::class);

        $this->mode = ChannelMode::Transactional;
    }

    /**
     * @throws \Throwable
     */
    public function txCommit(): void
    {
        if (!$this->mode->transactional()) {
            throw Exception\ChannelIsNotTransactional::for($this->channelId);
        }

        $this->connection->writeFrame(Protocol\Method::txCommit($this->channelId));

        $this->await(Frame\TxCommitOk::class);
    }

    /**
     * @throws \Throwable
     */
    public function txRollback(): void
    {
        if (!$this->mode->transactional()) {
            throw Exception\ChannelIsNotTransactional::for($this->channelId);
        }

        $this->connection->writeFrame(Protocol\Method::txRollback($this->channelId));

        $this->await(Frame\TxRollbackOk::class);
    }

    /**
     * @throws \Throwable
     */
    public function confirmSelect(bool $noWait = false): void
    {
        if ($this->mode->transactional()) {
            throw Exception\ChannelModeIsImpossible::inTransactional($this->channelId);
        }

        if ($this->mode->confirming()) {
            throw Exception\ChannelModeIsImpossible::alreadyConfirming($this->channelId);
        }

        $this->connection->writeFrame(Protocol\Method::confirmSelect($this->channelId, $noWait));

        if (!$noWait) {
            $this->await(Frame\ConfirmSelectOk::class);
        }

        $this->mode = ChannelMode::Confirm;
        $this->confirms->listen();
        $this->boundedReturns->listen();
    }

    /**
     * @param ReturnCallback $callback
     */
    public function onReturn(callable $callback): void
    {
        $this->returns->addReturnCallback($callback);
    }

    public function isClosed(): bool
    {
        return $this->closed?->await() ?? false;
    }

    /**
     * @param non-negative-int $replyCode
     */
    public function close(int $replyCode = 200, string $replyText = '', ?Cancellation $cancellation = null): void
    {
        ($this->closed ??= new Sync\Once(weakClosure(fn(): bool => $this->doClose($replyCode, $replyText))))->await($cancellation);
    }

    /**
     * @throws \Throwable
     */
    public function flow(bool $active): void
    {
        $this->connection->writeFrame(Protocol\Method::channelFlow($this->channelId, $active));

        $this->await(Frame\ChannelFlowOk::class);
    }

    public function abandon(\Throwable $e): void
    {
        $this->hooks->reject($this->channelId, $e);
        $this->hooks->unsubscribe($this->channelId);
        $this->cancellations->cancelAll(error: $e);
        $this->closed = new Sync\Once(static fn(): bool => true);
    }

    /**
     * @return iterable<array-key, Frame>
     */
    private function doPublish(
        Message $message,
        string $exchange = '',
        string $routingKey = '',
        bool $mandatory = false,
        bool $immediate = false,
    ): iterable {
        yield Protocol\Method::basicPublish(
            channelId: $this->channelId,
            exchange: $exchange,
            routingKey: $routingKey,
            mandatory: $mandatory,
            immediate: $immediate,
        );

        yield new Protocol\Header(
            channelId: $this->channelId,
            classId: Protocol\ClassType::BASIC,
            properties: MessageProperties::fromMessage($message),
        );

        foreach (Internal\chunks($message->body, $this->properties->maxFrame()) as $chunk) {
            yield new Protocol\Body(
                channelId: $this->channelId,
                body: $chunk,
            );
        }
    }

    /**
     * @template T of Frame
     * @param class-string<T> $frameType
     * @return T
     */
    private function await(
        string $frameType,
        Cancellation $cancellation = new NullCancellation(),
    ): Frame {
        return $this->hooks
            ->oneshot($this->channelId, $frameType)
            ->await($cancellation);
    }

    /**
     * @param non-negative-int $replyCode
     */
    private function doClose(int $replyCode = 200, string $replyText = ''): bool
    {
        $this->supervisor->stop();

        $this->connection->writeFrame(Protocol\Method::channelClose($this->channelId, $replyCode, $replyText));

        $this->await(Frame\ChannelCloseOk::class);

        return true;
    }
}
