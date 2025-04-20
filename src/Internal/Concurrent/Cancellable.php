<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Concurrent;

/**
 * @internal
 */
interface Cancellable
{
    public function cancel(): void;
}
