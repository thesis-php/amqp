<?php

declare(strict_types=1);

namespace Thesis\Amqp;

use Amp\Cancellation;
use Thesis\Amqp\Internal\Hooks;
use Thesis\Amqp\Internal\Io\AmqpConnection;
use Thesis\Amqp\Internal\Io\AmqpConnectionFactory;
use Thesis\Amqp\Internal\Io\ChannelFactory;
use Thesis\Amqp\Internal\Properties;
use Thesis\Sync;

/**
 * @api
 */
final class Client
{
    private readonly AmqpConnectionFactory $connectionFactory;

    private readonly ChannelFactory $channelFactory;

    /** @var ?Sync\Once<AmqpConnection> */
    private ?Sync\Once $connection = null;

    /** @var ?Sync\Once<void> */
    private ?Sync\Once $disconnection = null;

    private readonly Properties $properties;

    private readonly Hooks $hooks;

    public function __construct(
        public readonly Config $config,
    ) {
        $this->properties = Properties::createDefault();
        $this->hooks = Hooks::create();

        $this->connectionFactory = new AmqpConnectionFactory(
            $this->config,
            $this->properties,
            $this->hooks,
        );

        $this->channelFactory = new ChannelFactory(
            $this->properties,
            $this->hooks,
        );
    }

    public function connect(?Cancellation $cancellation = null): void
    {
        $this->connection($cancellation);
    }

    /**
     * @param non-negative-int $replyCode
     */
    public function disconnect(
        int $replyCode = 200,
        string $replyText = '',
        ?Cancellation $cancellation = null,
    ): void {
        $connection = $this->connection?->await($cancellation);

        if ($connection === null) {
            return;
        }

        $this->disconnection ??= new Sync\Once(function () use ($connection, $replyCode, $replyText, $cancellation): void {
            $this->channelFactory->close($replyCode, $replyText);
            $this->connectionFactory->close($connection, $replyCode, $replyText, $cancellation);
            $this->connection = null;
        });

        try {
            $this->disconnection->await($cancellation);
        } finally {
            $this->disconnection = null;
        }
    }

    public function channel(?Cancellation $cancellation = null): Channel
    {
        return $this->channelFactory->open(
            $this->connection($cancellation),
            $cancellation,
        );
    }

    private function connection(?Cancellation $cancellation = null): AmqpConnection
    {
        return ($this->connection ??= new Sync\Once($this->connectionFactory->connect(...)))->await($cancellation);
    }

    public function __destruct()
    {
        if (\PHP_VERSION_ID >= 80400) {
            $this->disconnect();
        }

        $this->hooks->complete();
    }
}
