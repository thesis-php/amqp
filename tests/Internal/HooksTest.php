<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Thesis\Amqp\Internal\Protocol\Frame\ConnectionStart;
use Thesis\Amqp\Internal\Protocol\Request;

#[CoversClass(Hooks::class)]
final class HooksTest extends TestCase
{
    public function testFutureComplete(): void
    {
        $hooks = new Hooks();
        $future = $hooks->oneshot(0, ConnectionStart::class);
        self::assertFalse($future->isComplete());

        $hooks->emit(new Request(0, new ConnectionStart(0, 9, ['version' => '3.12', 'platform' => 'Erlang OTP', 'cluster_name' => 'rabbit'])));
        self::assertTrue($future->isComplete());
        self::assertEquals(new ConnectionStart(0, 9, ['version' => '3.12', 'platform' => 'Erlang OTP', 'cluster_name' => 'rabbit']), $future->await());
    }

    public function testError(): void
    {
        $hooks = new Hooks();
        $future = $hooks->oneshot(0, ConnectionStart::class);

        $hooks->error(new \Exception('Error.'));
        self::assertCount(0, $hooks);
        self::expectException(\Exception::class);
        self::expectExceptionMessage('Error.');
        $future->await();
    }

    public function testComplete(): void
    {
        $hooks = new Hooks();
        $future = $hooks->oneshot(0, ConnectionStart::class);

        $hooks->complete();
        self::assertCount(0, $hooks);
        self::assertFalse($future->isComplete());
    }
}
