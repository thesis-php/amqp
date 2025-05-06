<?php

declare(strict_types=1);

namespace Thesis\Amqp;

use Amp\Cancellation;
use Amp\CancelledException;

/**
 * @api
 * @template T
 * @template-extends \IteratorAggregate<array-key, T>
 */
interface Iterator extends \IteratorAggregate
{
    /**
     * @throws \Throwable
     */
    public function complete(bool $noWait = false): void;

    /**
     * @throws \Throwable
     */
    public function cancel(\Throwable $e, bool $noWait = false): void;

    /**
     * @throws CancelledException
     */
    public function continue(?Cancellation $cancellation = null): bool;

    /**
     * @return T
     */
    public function value(): mixed;
}
