<?php

declare(strict_types=1);

namespace Thesis\Amqp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Thesis\Amqp\Exception\AuthenticationMechanismIsNotSupported;
use Thesis\Amqp\Internal\Protocol\Auth\AMQPlain;
use Thesis\Amqp\Internal\Protocol\Auth\Plain;

#[CoversClass(Config::class)]
final class ConfigTest extends TestCase
{
    public function testDefault(): void
    {
        $config = Config::default();
        self::assertSame(Scheme::amqp, $config->scheme);
        self::assertSame('guest', $config->user);
        self::assertSame('guest', $config->password);
        self::assertSame(['localhost:5672'], $config->urls);
        self::assertSame(60, $config->heartbeat);
        self::assertSame(0xFFFF, $config->channelMax);
        self::assertEquals(10, $config->connectionTimeout);
        self::assertNull($config->certFile);
        self::assertNull($config->keyFile);
        self::assertNull($config->cacertFile);
        self::assertCount(0, $config->authMechanisms);
        self::assertTrue($config->tcpNoDelay);
    }

    public function testUriParsed(): void
    {
        $config = Config::fromURI('amqp://guest:guest@localhost:5673/');
        self::assertSame(Scheme::amqp, $config->scheme);
        self::assertSame('guest', $config->user);
        self::assertSame('guest', $config->password);
        self::assertSame(['localhost:5673'], $config->urls);
        self::assertSame(60, $config->heartbeat);
        self::assertSame(0xFFFF, $config->channelMax);
        self::assertEquals(10, $config->connectionTimeout);
        self::assertNull($config->certFile);
        self::assertNull($config->keyFile);
        self::assertNull($config->cacertFile);
        self::assertCount(0, $config->authMechanisms);
    }

    public function testClusterUriParsed(): void
    {
        $config = Config::fromURI('amqp://guest:guest@localhost:5673,localhost:5674/test?channel_max=8');
        self::assertSame(Scheme::amqp, $config->scheme);
        self::assertSame('guest', $config->user);
        self::assertSame('guest', $config->password);
        self::assertSame(['localhost:5673', 'localhost:5674'], $config->urls);
        self::assertSame('/test', $config->vhost);
        self::assertSame(8, $config->channelMax);
    }

    public function testUriParsedWithTLS(): void
    {
        $config = Config::fromURI('amqps://foo.bar/?certfile=/foo/%D0%BF%D1%80%D0%B8%D0%B2%D0%B5%D1%82/cert.pem&keyfile=/foo/%E4%BD%A0%E5%A5%BD/key.pem&cacertfile=C:%5Ccerts%5Cca.pem&server_name_indication=example.com');
        self::assertSame(Scheme::amqps, $config->scheme);
        self::assertSame(['foo.bar:5672'], $config->urls);
        self::assertSame('guest', $config->user);
        self::assertSame('guest', $config->password);
        self::assertSame('/', $config->vhost);
        self::assertSame('/foo/привет/cert.pem', $config->certFile);
        self::assertSame('/foo/你好/key.pem', $config->keyFile);
        self::assertSame('C:\certs\ca.pem', $config->cacertFile);
        self::assertSame('example.com', $config->serverName);
        self::assertCount(0, $config->authMechanisms);
        self::assertSame(60, $config->heartbeat);
        self::assertSame(0xFFFF, $config->channelMax);
        self::assertEquals(10, $config->connectionTimeout);
    }

    public function testUriParsedWithParameters(): void
    {
        $config = Config::fromURI('amqps://foo.bar/test?auth_mechanism=plain&auth_mechanism=amqplain&heartbeat=2&connection_timeout=20&channel_max=8&frame_max=10&tcp_nodelay=false');
        self::assertSame(Scheme::amqps, $config->scheme);
        self::assertSame(['foo.bar:5672'], $config->urls);
        self::assertSame('guest', $config->user);
        self::assertSame('guest', $config->password);
        self::assertSame('/test', $config->vhost);
        self::assertNull($config->certFile);
        self::assertNull($config->keyFile);
        self::assertNull($config->cacertFile);
        self::assertNull($config->serverName);
        self::assertSame(['plain', 'amqplain'], $config->authMechanisms);
        self::assertSame(2, $config->heartbeat);
        self::assertSame(8, $config->channelMax);
        self::assertEquals(20, $config->connectionTimeout);
        self::assertSame(10, $config->frameMax);
        self::assertCount(2, $config->sasl());
        self::assertInstanceOf(Plain::class, $config->sasl()[0]);
        self::assertInstanceOf(AMQPlain::class, $config->sasl()[1]);
        self::assertFalse($config->tcpNoDelay);
    }

    public function testInvalidAuthMechanism(): void
    {
        self::expectException(AuthenticationMechanismIsNotSupported::class);
        self::expectExceptionMessage('Authentication client mechanism "invalid" is not supported.');
        Config::fromURI('amqps://foo.bar/test?auth_mechanism=invalid&auth_mechanism=amqplain&heartbeat=2&connection_timeout=5000&channel_max=8&frame_max=10');
    }

    public function testNegativeQueryParameters(): void
    {
        $config = Config::fromURI('amqps://foo.bar/test?heartbeat=-2&connection_timeout=-5000&channel_max=-8&frame_max=-8');
        self::assertSame(60, $config->heartbeat);
        self::assertSame(0xFFFF, $config->channelMax);
        self::assertEquals(10, $config->connectionTimeout);
        self::assertSame(0xFFFF, $config->frameMax);
    }

    public function testMaxQueryParameters(): void
    {
        $config = Config::fromURI('amqps://foo.bar/test?channel_max=65536&frame_max=65536');
        self::assertSame(0xFFFF, $config->channelMax);
        self::assertSame(0xFFFF, $config->frameMax);
    }

    public function testZeroQueryParameters(): void
    {
        $config = Config::fromURI('amqps://foo.bar/test?channel_max=0&frame_max=0&heartbeat=0&connection_timeout=0');
        self::assertSame(0xFFFF, $config->channelMax);
        self::assertSame(0xFFFF, $config->frameMax);
        self::assertSame(0, $config->heartbeat);
        self::assertEquals(10, $config->connectionTimeout);
    }
}
