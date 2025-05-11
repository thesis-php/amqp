<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Io;

use Amp\Cancellation;
use Thesis\Amqp\Channel;
use Thesis\Amqp\Exception;
use Thesis\Amqp\Internal\Hooks;
use Thesis\Amqp\Internal\Properties;
use Thesis\Amqp\Internal\Protocol;
use Thesis\Amqp\Internal\Protocol\Frame;

/**
 * @internal
 */
final class ChannelFactory
{
    /** @var non-negative-int */
    private int $channelId = 1;

    /** @var array<non-negative-int, Channel> */
    private array $channels = [];

    public function __construct(
        private readonly Properties $properties,
        private readonly Hooks $hooks,
    ) {
        $this->hooks->anyOf(0, Frame\ConnectionClose::class, function (Frame\ConnectionClose $close): void {
            $error = Exception\ConnectionWasClosed::byServer($close->replyCode, $close->replyText);

            foreach ($this->channels as $channel) {
                $channel->abandon($error);
            }

            $this->channels = [];
        });
    }

    public function open(AmqpConnection $connection, ?Cancellation $cancellation = null): Channel
    {
        $channelId = $this->allocateChannelId();
        $this->openChannel($connection, $channelId, $cancellation);

        return $this->channels[$channelId] = new Channel(
            $channelId,
            $connection,
            $this->properties,
            $this->hooks,
        );
    }

    /**
     * @param non-negative-int $replyCode
     */
    public function close(
        int $replyCode,
        string $replyText,
    ): void {
        try {
            foreach ($this->channels as $channel) {
                $channel->close($replyCode, $replyText);
            }
        } finally {
            $this->channels = [];
            $this->channelId = 1;
        }
    }

    /**
     * @param non-negative-int $channelId
     */
    private function openChannel(AmqpConnection $connection, int $channelId, ?Cancellation $cancellation = null): void
    {
        $connection->writeFrame(Protocol\Method::channelOpen($channelId));

        $this->hooks->oneshot($channelId, Frame\ChannelOpenOkFrame::class)->await($cancellation);

        $this->hooks->anyOf(
            $channelId,
            [Frame\ChannelCloseOk::class, Frame\ChannelClose::class],
            function (Frame\ChannelCloseOk|Frame\ChannelClose $frame) use ($channelId, $connection): void {
                $channel = $this->channels[$channelId] ?? null;

                if ($channel !== null) {
                    unset($this->channels[$channelId]);

                    if ($frame instanceof Frame\ChannelClose) {
                        $connection->writeFrame(Protocol\Method::channelCloseOk($channelId));
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
}
