<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Thesis\Amqp\Internal\Protocol\Frame\BasicAck;
use Thesis\Amqp\Internal\Protocol\Frame\BasicNack;
use Thesis\Amqp\Internal\Protocol\Request;
use Thesis\Amqp\PublishConfirmation;
use Thesis\Amqp\PublishResult;

#[CoversClass(ConfirmationListener::class)]
final class ConfirmationListenerTest extends TestCase
{
    #[TestWith([PublishResult::Acked, new BasicAck(1, false)])]
    #[TestWith([PublishResult::Nacked, new BasicNack(1, false, false)])]
    public function testConfirmOne(PublishResult $result, BasicAck|BasicNack $frame): void
    {
        $hooks = new Hooks();
        $listener = new ConfirmationListener($hooks, 1);
        $listener->listen();

        $confirmation1 = $listener->newConfirmation();
        self::assertSame(1, $confirmation1->deliveryTag);
        self::assertSame(PublishResult::Waiting, $confirmation1->result());
        self::assertCount(1, $listener);

        $hooks->emit(new Request(1, $frame));
        self::assertSame($result, $confirmation1->await());
        self::assertSame($result, $confirmation1->result());
    }

    #[TestWith([PublishResult::Acked, new BasicAck(2, true)])]
    #[TestWith([PublishResult::Nacked, new BasicNack(2, true, false)])]
    public function testConfirmMultiple(PublishResult $result, BasicAck|BasicNack $frame): void
    {
        $hooks = new Hooks();
        $listener = new ConfirmationListener($hooks, 1);
        $listener->listen();

        $confirmation1 = $listener->newConfirmation();
        self::assertSame(1, $confirmation1->deliveryTag);
        self::assertSame(PublishResult::Waiting, $confirmation1->result());
        self::assertCount(1, $listener);

        $confirmation2 = $listener->newConfirmation();
        self::assertSame(2, $confirmation2->deliveryTag);
        self::assertSame(PublishResult::Waiting, $confirmation2->result());
        self::assertCount(2, $listener);

        $hooks->emit(new Request(1, $frame));
        self::assertSame($result, $confirmation1->await());
        self::assertSame($result, $confirmation1->result());
        self::assertSame($result, $confirmation2->await());
        self::assertSame($result, $confirmation2->result());
    }

    public function testCancelConfirmation(): void
    {
        $hooks = new Hooks();
        $listener = new ConfirmationListener($hooks, 1);
        $listener->listen();

        $confirmation1 = $listener->newConfirmation();
        $confirmation1->cancel();
        self::assertSame(PublishResult::Canceled, $confirmation1->await());
        self::assertSame(PublishResult::Canceled, $confirmation1->result());
    }

    public function testAwaitAllConfirmations(): void
    {
        $hooks = new Hooks();
        $listener = new ConfirmationListener($hooks, 1);
        $listener->listen();

        $confirmation1 = $listener->newConfirmation();
        $confirmation2 = $listener->newConfirmation();

        $hooks->emit(new Request(1, new BasicAck(1, false)));
        $hooks->emit(new Request(1, new BasicAck(2, false)));

        $acks = PublishConfirmation::awaitAll([$confirmation1, $confirmation2]);
        self::assertSame([1 => PublishResult::Acked, 2 => PublishResult::Acked], iterator_to_array($acks));
    }
}
