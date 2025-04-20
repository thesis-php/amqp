<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Concurrent;

use Amp\Future;

/**
 * @template-covariant T = mixed
 */
interface Awaitable
{
    /**
     * @return Future<T>
     */
    public function future(): Future;
}
