<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Rpc;

use Amp\Cancellation;
use Thesis\Amqp\Client;
use Thesis\Amqp\RpcConfig;
use Thesis\Amqp\RpcHandler;

/**
 * @internal
 */
function createHandler(
    Client $client,
    RpcConfig $config,
    ?Cancellation $cancellation = null,
): RpcHandler {
    $publishChannel = $client->channel($cancellation);

    if ($config->confirms) {
        $publishChannel->confirmSelect();
    }

    $consumerClient = new Client($client->config);
    $consumeChannel = $consumerClient->channel($cancellation);

    return new RpcHandler(
        publishChannel: $publishChannel,
        consumeChannel: $consumeChannel,
        config: $config,
        cancel: $consumerClient->disconnect(...),
    );
}

/**
 * @internal
 * @param positive-int $length
 * @return non-empty-string
 */
function generateReplyTo(int $length = 10): string
{
    $id = generateId($length);

    return "thesis.rpc.{$id}";
}

/**
 * @internal
 * @param positive-int $length
 * @return non-empty-string
 */
function generateId(int $length = 10): string
{
    return bin2hex(random_bytes($length));
}
