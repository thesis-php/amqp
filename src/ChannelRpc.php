<?php

declare(strict_types=1);

namespace Thesis\Amqp;

use Amp\Cancellation;

/**
 * @api
 */
interface ChannelRpc
{
    public function request(
        Message $message,
        string $exchange = '',
        string $routingKey = '',
        bool $mandatory = false,
        bool $immediate = false,
        ?Cancellation $cancellation = null,
    ): Message;

    public function close(): void;
}
