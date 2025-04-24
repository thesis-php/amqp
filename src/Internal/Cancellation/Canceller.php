<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Cancellation;

use Amp\Cancellation;
use Amp\DeferredCancellation;

/**
 * @internal
 */
final class Canceller
{
    private bool $cancelled = false;

    private readonly DeferredCancellation $deferred;

    /**
     * @param callable(bool): void $complete
     * @param callable(\Throwable, bool): void $cancel
     */
    public function __construct(
        private readonly mixed $complete,
        private readonly mixed $cancel,
    ) {
        $this->deferred = new DeferredCancellation();
    }

    public function cancel(bool $noWait = false, ?\Throwable $e = null): void
    {
        if ($this->cancelled) {
            return;
        }

        try {
            if ($e !== null) {
                ($this->cancel)($e, $noWait);
            } else {
                ($this->complete)($noWait);
            }

            $this->deferred->cancel();
        } finally {
            $this->cancelled = true;
        }
    }

    public function cancellation(): Cancellation
    {
        return $this->deferred->getCancellation();
    }
}
