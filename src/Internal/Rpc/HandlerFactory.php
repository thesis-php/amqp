<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Rpc;

use Thesis\Amqp\ChannelRpcConfig;
use Thesis\Amqp\Client;

/**
 * @internal
 */
final readonly class HandlerFactory
{
    public function __construct(
        private Client $client,
    ) {}

    public function create(ChannelRpcConfig $config): Handler
    {
        $publishChannel = $this->client->channel();

        if ($config->confirms) {
            $publishChannel->confirmSelect();
        }

        $consumerClient = new Client($this->client->config);
        $consumeChannel = $consumerClient->channel();

        return new Handler(
            publishChannel: $publishChannel,
            consumeChannel: $consumeChannel,
            config: $config,
            cancel: $consumerClient->disconnect(...),
        );
    }
}
