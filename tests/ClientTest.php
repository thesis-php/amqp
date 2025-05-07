<?php

declare(strict_types=1);

namespace Thesis\Amqp;

use Amp\ByteStream\ClosedException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

#[CoversClass(Client::class)]
final class ClientTest extends TestCase
{
    #[DoesNotPerformAssertions]
    public function testDisconnectDoesNotThrowIfClientIsNotConnected(): void
    {
        $client = new Client(Config::default());
        $client->disconnect();
    }

    public function testConnectDisconnectExplicit(): void
    {
        $client = new Client(Config::default());
        $channel = $client->channel();
        $client->disconnect();

        self::expectException(ClosedException::class);
        $channel->confirmSelect();
    }

    public function testConnectDisconnectOnDestructor(): void
    {
        if (PHP_VERSION_ID < 80400) {
            self::markTestSkipped('php 8.4 is required to run this test.');
        }

        $client = new Client(Config::default());
        $channel = $client->channel();

        unset($client);

        self::expectException(ClosedException::class);
        $channel->confirmSelect();
    }
}
