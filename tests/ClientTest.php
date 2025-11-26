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
        $client = $this->createClient();
        $client->disconnect();
    }

    public function testConnectDisconnectExplicit(): void
    {
        $client = $this->createClient();
        $channel = $client->channel();
        $client->disconnect();

        self::expectException(ConnectionIsClosed::class);
        $channel->confirmSelect();
    }

    public function testClientGarbageCollection(): void
    {
        $gcEnabled = gc_enabled();

        try {
            gc_disable();
            $client = $this->createClient();
            $channel = $client->channel();
            $weakClient = \WeakReference::create($client);
            $weakChannel = \WeakReference::create($channel);

            unset($client, $channel);

            self::assertTrue($weakClient->get() === null, 'Client has circular references and cannot be garbage collected');
            self::assertTrue($weakChannel->get() === null, 'Channel has circular references and cannot be garbage collected');
        } finally {
            if ($gcEnabled) {
                gc_enable();
            }
        }
    }

    public function testClosedChannelGarbageCollection(): void
    {
        $gcEnabled = gc_enabled();

        try {
            gc_disable();
            $client = $this->createClient();
            $channel = $client->channel();
            $weakChannel = \WeakReference::create($channel);
            $channel->close();

            unset($channel);

            self::assertTrue($weakChannel->get() === null, 'Channel has circular references and cannot be garbage collected');
        } finally {
            if ($gcEnabled) {
                gc_enable();
            }
        }
    }

    #[RequiresPhp('8.4')]
    public function testConnectDisconnectOnDestructor(): void
    {
        $client = $this->createClient();
        $channel = $client->channel();

        unset($client);

        self::expectException(ConnectionIsClosed::class);
        $channel->confirmSelect();
    }

    private static function createClient(): Client
    {
        return new Client(new Config(urls: ['rabbitmq:5672']));
    }
}
