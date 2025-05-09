<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Delivery;

use Thesis\Amqp\Channel;
use Thesis\Amqp\DeliveryMessage;
use Thesis\Amqp\Internal\Hooks;
use Thesis\Amqp\Internal\Protocol\Frame;
use Thesis\Amqp\Message;

/**
 * @internal
 * @phpstan-type ConsumeListener = callable(DeliveryMessage, Channel): void
 * @phpstan-type ReturnListener = callable(DeliveryMessage, Channel): void
 * @phpstan-type GetListener = callable(null|DeliveryMessage, Channel): void
 */
final class DeliverySupervisor
{
    private const int WAIT = 0;
    private const int HEADER = 1;
    private const int BODY = 2;

    /**
     * @var \WeakReference<Channel>
     */
    private readonly \WeakReference $weakChannel;

    /** @var self::* */
    private int $step = self::WAIT;

    private ?Frame\BasicDeliver $delivery = null;

    private ?Frame\BasicGetOk $get = null;

    private ?Frame\BasicReturn $return = null;

    private ?Frame\ContentHeader $header = null;

    /** @var non-negative-int */
    private int $messageSize = 0;

    private string $message = '';

    /** @var list<ConsumeListener> */
    private array $consumeListeners = [];

    /** @var list<ReturnListener> */
    private array $returnListeners = [];

    /** @var list<GetListener> */
    private array $getListeners = [];

    /** @var list<callable(): void> */
    private array $shutdownListeners = [];

    /**
     * @param non-negative-int $channelId
     */
    public function __construct(
        Channel $channel,
        private readonly Hooks $hooks,
        private readonly int $channelId,
    ) {
        $this->weakChannel = \WeakReference::create($channel);
    }

    public function run(): void
    {
        $this->subscribe(Frame\BasicGetEmpty::class, $this->onBasicGetEmpty(...));
        $this->subscribe(Frame\BasicGetOk::class, $this->onBasicGetOk(...));
        $this->subscribe(Frame\BasicDeliver::class, $this->onBasicDeliver(...));
        $this->subscribe(Frame\BasicReturn::class, $this->onBasicReturn(...));
        $this->subscribe(Frame\ContentHeader::class, $this->onContentHeader(...));
        $this->subscribe(Frame\ContentBody::class, $this->onContentBody(...));
    }

    /**
     * @param ConsumeListener $listener
     */
    public function addConsumeListener(callable $listener): void
    {
        $this->consumeListeners[] = $listener;
    }

    /**
     * @param ReturnListener $listener
     */
    public function addReturnListener(callable $listener): void
    {
        $this->returnListeners[] = $listener;
    }

    /**
     * @param GetListener $listener
     */
    public function addGetListener(callable $listener): void
    {
        $this->getListeners[] = $listener;
    }

    /**
     * @param callable(): void $listener
     */
    public function addShutdownListener(callable $listener): void
    {
        $this->shutdownListeners[] = $listener;
    }

    public function stop(): void
    {
        foreach ($this->shutdownListeners as $shutdownListener) {
            $shutdownListener();
        }

        [
            $this->consumeListeners,
            $this->returnListeners,
            $this->getListeners,
            $this->shutdownListeners,
        ] = [[], [], [], []];
    }

    private function onBasicGetEmpty(): void
    {
        foreach ($this->getListeners as $listener) {
            $listener(null, $this->channel());
        }
    }

    private function onBasicGetOk(Frame\BasicGetOk $get): void
    {
        if ($this->step === self::WAIT) {
            [$this->get, $this->step] = [$get, self::HEADER];
        }
    }

    private function onBasicDeliver(Frame\BasicDeliver $delivery): void
    {
        if ($this->step === self::WAIT) {
            [$this->delivery, $this->step] = [$delivery, self::HEADER];
        }
    }

    private function onBasicReturn(Frame\BasicReturn $return): void
    {
        if ($this->step === self::WAIT) {
            [$this->return, $this->step] = [$return, self::HEADER];
        }
    }

    private function onContentHeader(Frame\ContentHeader $header): void
    {
        if ($this->step === self::HEADER) {
            $this->header = $header;
            $this->step = self::BODY;
            $this->messageSize = $this->header->bodySize;

            $this->runListeners();
        }
    }

    private function onContentBody(Frame\ContentBody $body): void
    {
        if ($this->step === self::BODY) {
            $this->message .= $body->body;
            $this->messageSize = max($this->messageSize - \strlen($body->body), 0);

            $this->runListeners();
        }
    }

    private function runListeners(): void
    {
        if ($this->messageSize !== 0) {
            return;
        }

        \assert($this->delivery !== null || $this->return !== null || $this->get !== null, 'delivery, return or get must not be empty.');
        \assert($this->header !== null, 'header must not be empty.');

        // You cannot call ack/nack/reject on a returned message.
        $noAction = static function (): void {};

        $channel = $this->channel();

        $delivery = new DeliveryMessage(
            ack: $this->return !== null ? $noAction : $channel->ack(...),
            nack: $this->return !== null ? $noAction : $channel->nack(...),
            reject: $this->return !== null ? $noAction : $channel->reject(...),
            message: new Message(
                body: $this->message,
                headers: $this->header->properties->headers,
                contentType: $this->header->properties->contentType,
                contentEncoding: $this->header->properties->contentEncoding,
                deliveryMode: $this->header->properties->deliveryMode,
                priority: $this->header->properties->priority,
                correlationId: $this->header->properties->correlationId,
                replyTo: $this->header->properties->replyTo,
                expiration: $this->header->properties->expiration,
                messageId: $this->header->properties->messageId,
                timestamp: $this->header->properties->timestamp,
                type: $this->header->properties->type,
                userId: $this->header->properties->userId,
                appId: $this->header->properties->appId,
            ),
            exchange: $this->delivery->exchange ?? $this->get->exchange ?? $this->return->exchange ?? '',
            routingKey: $this->delivery->routingKey ?? $this->get->routingKey ?? $this->return->routingKey ?? '',
            deliveryTag: $this->delivery->deliveryTag ?? $this->get->deliveryTag ?? 0,
            consumerTag: $this->delivery->consumerTag ?? '',
            redelivered: $this->delivery->redelivered ?? $this->get->redelivered ?? false,
            returned: $this->return !== null,
        );

        $listeners = match (true) {
            $this->delivery !== null => $this->consumeListeners,
            $this->get !== null => $this->getListeners,
            $this->return !== null => $this->returnListeners,
            default => [],
        };

        foreach ($listeners as $listener) {
            $listener($delivery, $channel);
        }

        $this->get = null;
        $this->delivery = null;
        $this->return = null;
        $this->header = null;
        $this->message = '';
        $this->step = self::WAIT;
    }

    /**
     * @template T of Frame
     * @param class-string<T> $frameType
     * @param \Closure(T): void $callback
     */
    private function subscribe(string $frameType, \Closure $callback): void
    {
        $this->hooks->subscribe($this->channelId, $frameType, $callback);
    }

    private function channel(): Channel
    {
        return $this->weakChannel->get() ?? throw new \LogicException('Channel has been garbage collected.');
    }
}
