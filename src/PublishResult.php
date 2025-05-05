<?php

declare(strict_types=1);

namespace Thesis\Amqp;

/**
 * @api
 */
enum PublishResult
{
    case Unrouted;
    case Acked;
    case Nacked;
    case Canceled;
    case Waiting;
}
