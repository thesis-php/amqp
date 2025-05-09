<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Io;

use Amp\Cancellation;
use Amp\Socket;
use Thesis\Amqp\Config;
use Thesis\Amqp\Exception;
use Thesis\Amqp\Internal\Hooks;
use Thesis\Amqp\Internal\Properties;
use Thesis\Amqp\Internal\Protocol;
use Thesis\Amqp\Internal\Protocol\Auth;
use Thesis\Amqp\Internal\Protocol\Frame;
use Thesis\Amqp\Scheme;

/**
 * @internal
 */
final readonly class AmqpConnectionFactory
{
    public function __construct(
        private Config $config,
        private Properties $properties,
        private Hooks $hooks,
    ) {}

    public function connect(): AmqpConnection
    {
        $connection = $this->createConnection();

        $start = $connection->rpc(Frame\ProtocolHeader::frame, Frame\ConnectionStart::class);

        $tune = $connection->rpc(
            Protocol\Method::connectionStartOk($this->properties->toArray(), Auth\Mechanism::select(
                $this->config->sasl(),
                $start->mechanisms,
            )),
            Frame\ConnectionTune::class,
        );

        [$heartbeat, $channelMax, $frameMax] = [
            $this->config->heartbeat($tune->heartbeat),
            $this->config->channelMax($tune->channelMax),
            $this->config->frameMax($tune->frameMax),
        ];

        $connection->rpc(
            Protocol\Method::connectionTuneOk($channelMax, $frameMax, $heartbeat),
        );

        $this->properties->tune($channelMax, $frameMax);

        if ($heartbeat > 0) {
            $connection->heartbeat($heartbeat);
        }

        $connection->rpc(
            Protocol\Method::connectionOpen($this->config->vhost),
            Frame\ConnectionOpenOk::class,
        );

        $connection->ioLoop($this->hooks);

        $this->hooks->anyOf(0, Frame\ConnectionClose::class, static function () use ($connection): void {
            $connection->writeFrame(Protocol\Method::connectionCloseOk());
            $connection->close();
        });

        return $connection;
    }

    /**
     * @param non-negative-int $replyCode
     */
    public function close(
        AmqpConnection $connection,
        int $replyCode,
        string $replyText,
        ?Cancellation $cancellation = null,
    ): void {
        $connection->writeFrame(Protocol\Method::connectionClose($replyCode, $replyText));

        $this->hooks->oneshot(0, Frame\ConnectionCloseOk::class)->await($cancellation);

        $connection->close();
    }

    private function createConnection(): AmqpConnection
    {
        $exceptions = [];

        foreach ($this->config->connectionUrls() as $url) {
            try {
                return new AmqpConnection($this->createSocket($url));
            } catch (\Throwable $e) {
                $exceptions[] = "{$url}: {$e->getMessage()}";
            }
        }

        throw new Exception\ConnectionNotAvailable(
            \sprintf('No available amqp host: %s.', implode('; ', $exceptions)),
        );
    }

    /**
     * @param non-empty-string $url
     */
    private function createSocket(string $url): Socket\Socket
    {
        $context = (new Socket\ConnectContext())
            ->withConnectTimeout($this->config->connectionTimeout);

        if ($this->config->tcpNoDelay) {
            $context = $context->withTcpNoDelay();
        }

        $socket = Socket\connect($url, $context);

        if ($this->config->scheme === Scheme::amqps) {
            $socket->setupTls();
        }

        return $socket;
    }
}
