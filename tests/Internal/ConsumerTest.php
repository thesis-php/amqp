<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal;

use Amp\DeferredFuture;
use Amp\Socket\Socket;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Thesis\Amqp\Channel;
use Thesis\Amqp\DeliveryMessage;
use Thesis\Amqp\Internal\Delivery\Consumer;
use Thesis\Amqp\Internal\Delivery\DeliverySupervisor;
use Thesis\Amqp\Internal\Io\AmqpConnection;
use Thesis\Amqp\Internal\Protocol\ClassType;
use Thesis\Amqp\Internal\Protocol\Frame\BasicDeliver;
use Thesis\Amqp\Internal\Protocol\Frame\ContentBody;
use Thesis\Amqp\Internal\Protocol\Frame\ContentHeader;
use Thesis\Amqp\Internal\Protocol\Request;
use Thesis\Amqp\Message;

#[CoversClass(Consumer::class)]
final class ConsumerTest extends TestCase
{
    public function testConsumerShutdown(): void
    {
        $hooks = Hooks::create();

        $channel = new Channel(
            1,
            new AmqpConnection(self::createStub(Socket::class)),
            Properties::createDefault(),
            $hooks,
        );

        $supervisor = new DeliverySupervisor($channel, $hooks, 1);
        $supervisor->run();

        $consumer = Consumer::create($supervisor, $channel);

        /** @var DeferredFuture<DeliveryMessage> $deferred */
        $deferred = new DeferredFuture();
        $consumer->register('abz', $deferred->complete(...));

        self::assertCount(1, [...$consumer]);

        $hooks->emit(new Request(1, new BasicDeliver('abz', 1, false, '', 'xxx')));
        $hooks->emit(new Request(1, new ContentHeader(ClassType::BASIC, 0, 3, 0, MessageProperties::fromMessage(new Message()))));
        $hooks->emit(new Request(1, new ContentBody('xxx')));

        self::assertInstanceOf(DeliveryMessage::class, $deferred->getFuture()->await());

        $supervisor->stop();

        self::assertCount(0, [...$consumer]);
    }
}
