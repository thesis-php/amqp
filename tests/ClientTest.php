<?php

declare(strict_types=1);

namespace Thesis\Amqp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;
use Thesis\Amqp\Exception\ConnectionIsClosed;

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

        self::expectException(ConnectionIsClosed::class);
        $channel->confirmSelect();
    }

    #[RequiresPhp('8.4')]
    public function testConnectDisconnectOnDestructor(): void
    {
        $client = new Client(Config::default());
        $channel = $client->channel();

        unset($client);

        self::expectException(ConnectionIsClosed::class);
        $channel->confirmSelect();
    }
}
