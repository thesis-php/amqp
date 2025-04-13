<?php

declare(strict_types=1);

namespace Thesis\Amqp;

use Amp\Cancellation;
use Amp\Future;
use Amp\NullCancellation;
use Amp\Socket;
use Thesis\Amqp\Exception\ConnectionNotAvailable;
use Thesis\Amqp\Internal\Hooks;
use Thesis\Amqp\Internal\Io\AmqpConnection;
use Thesis\Amqp\Internal\Properties;
use Thesis\Amqp\Internal\Protocol;
use Thesis\Amqp\Internal\Protocol\Auth;
use Thesis\Amqp\Internal\Protocol\Frame;
use function Amp\async;

/**
 * @api
 */
final class Client
{
    private ?AmqpConnection $connection = null;

    /** @var ?Future<void> */
    private ?Future $connectionFuture = null;

    /** @var non-negative-int */
    private int $channelId = 1;

    /** @var array<non-negative-int, Channel> */
    private array $channels = [];

    private readonly Properties $properties;

    private readonly Hooks $hooks;

    public function __construct(private readonly Config $config)
    {
        $this->properties = Properties::createDefault();
        $this->hooks = Hooks::create();
    }

    /**
     * @throws \Throwable
     */
    public function connect(): void
    {
        $this->connectionFuture?->await();

        if ($this->connection !== null) {
            return;
        }

        /** @var Future<void> $future */
        $future = async($this->doConnect(...));
        $this->connectionFuture = $future;

        try {
            $this->connectionFuture->await();
        } catch (\Throwable $e) {
            $this->connection = null;

            throw $e;
        } finally {
            $this->connectionFuture = null;
        }
    }

    /**
     * @param non-negative-int $replyCode
     * @throws \Throwable
     */
    public function disconnect(int $replyCode = 200, string $replyText = '', Cancellation $cancellation = new NullCancellation()): void
    {
        static $disconnecting = false;
        if ($disconnecting || $this->connection === null) {
            return;
        }

        $disconnecting = true;

        try {
            foreach ($this->channels as $channel) {
                $channel->close($replyCode, $replyText);
            }

            $this->connectionClose($replyCode, $replyText, $cancellation);
            $this->connection()->close();
        } finally {
            $disconnecting = false;

            $this->channels = [];
            $this->connection = null;
        }
    }

    /**
     * @throws \Throwable
     */
    public function channel(Cancellation $cancellation = new NullCancellation()): Channel
    {
        $this->connect();

        $channelId = $this->allocateChannelId();
        $this->openChannel($channelId, $cancellation);

        return $this->channels[$channelId] = new Channel(
            $channelId,
            $this->connection(),
            $this->properties,
            $this->hooks,
        );
    }

    /**
     * @param non-negative-int $replyCode
     * @throws \Throwable
     */
    private function connectionClose(int $replyCode, string $replyText = '', Cancellation $cancellation = new NullCancellation()): void
    {
        $this->connection()->writeFrame(Protocol\Method::connectionClose($replyCode, $replyText));

        $this->await(Frame\ConnectionCloseOk::class, cancellation: $cancellation);
    }

    /**
     * @param non-negative-int $channelId
     * @throws \Throwable
     */
    private function openChannel(int $channelId, Cancellation $cancellation = new NullCancellation()): void
    {
        $this->connection()->writeFrame(Protocol\Method::channelOpen($channelId));

        $this->await(Frame\ChannelOpenOkFrame::class, $channelId, $cancellation);

        $this->hooks->anyOf(
            $channelId,
            [Frame\ChannelCloseOk::class, Frame\ChannelClose::class],
            function (Frame\ChannelCloseOk|Frame\ChannelClose $frame) use ($channelId): void {
                $channel = $this->channels[$channelId] ?? null;

                if ($channel !== null) {
                    unset($this->channels[$channelId]);

                    if ($frame instanceof Frame\ChannelClose) {
                        $this->connection()->writeFrame(Protocol\Method::channelCloseOk($channelId));
                        $channel->abandon(new Exception\ChannelWasClosed($frame->replyCode, $frame->replyText));
                    }

                    $this->hooks->unsubscribe($channelId);
                }
            },
        );
    }

    /**
     * @return non-negative-int
     */
    private function allocateChannelId(): int
    {
        for ($id = $this->channelId; $id <= $this->properties->maxChannel(); ++$id) {
            if (!isset($this->channels[$id])) {
                $this->channelId = $id + 1;

                return $id;
            }
        }

        for ($id = 1; $id < $this->channelId; ++$id) {
            if (!isset($this->channels[$id])) {
                $this->channelId = $id + 1;

                return $id;
            }
        }

        throw Exception\NoAvailableChannel::forMaxChannel($this->properties->maxChannel());
    }

    /**
     * @throws Exception\ConnectionIsClosed
     */
    private function connection(): AmqpConnection
    {
        return $this->connection ?: throw new Exception\ConnectionIsClosed();
    }

    private function doConnect(): void
    {
        $this->connection = $this->createConnection();

        $start = $this->connection->rpc(Frame\ProtocolHeader::frame, Frame\ConnectionStart::class);

        $tune = $this->connection->rpc(
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

        $this->connection->rpc(
            Protocol\Method::connectionTuneOk($channelMax, $frameMax, $heartbeat),
        );

        $this->properties->tune($channelMax, $frameMax);

        if ($heartbeat > 0) {
            $this->connection->heartbeat($heartbeat);
        }

        $this->connection->rpc(
            Protocol\Method::connectionOpen($this->config->vhost),
            Frame\ConnectionOpenOk::class,
        );

        $this->connection->ioLoop($this->hooks);

        $this->hooks->oneshot(0, Frame\ConnectionClose::class)->map(function (Frame\ConnectionClose $close): void {
            $this->connection()->writeFrame(Protocol\Method::connectionCloseOk());
            $this->connection()->close();

            $error = Exception\ConnectionWasClosed::byServer($close->replyCode, $close->replyText);

            foreach ($this->channels as $channel) {
                $channel->abandon($error);
            }

            $this->channels = [];
            $this->connection = null;
        });
    }

    private function createConnection(): AmqpConnection
    {
        $exceptions = [];

        foreach ($this->config->connectionUrls() as $url) {
            try {
                return new AmqpConnection($this->createSocket($url));
            } catch (\Throwable $e) {
                $exceptions[$url] = $e->getMessage();
            }
        }

        throw new ConnectionNotAvailable(vsprintf('No available amqp host: %s.', [
            implode('; ', array_map(
                static fn(string $url, string $exception): string => "{$url}: {$exception}",
                array_keys($exceptions),
                array_values($exceptions),
            )),
        ]));
    }

    /**
     * @param non-empty-string $url
     * @throws \Throwable
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

    /**
     * @template T of Frame
     * @param non-negative-int $channelId
     * @param class-string<T> $frameType
     * @return T
     */
    private function await(
        string $frameType,
        int $channelId = 0,
        Cancellation $cancellation = new NullCancellation(),
    ): Frame {
        return $this->hooks
            ->oneshot($channelId, $frameType)
            ->await($cancellation);
    }

    public function __destruct()
    {
        if (\PHP_VERSION_ID >= 80400) {
            $this->disconnect();
        }
    }
}
