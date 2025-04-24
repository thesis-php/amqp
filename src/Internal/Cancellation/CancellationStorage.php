<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Cancellation;

/**
 * @internal
 */
final class CancellationStorage
{
    /** @var array<non-empty-string, Canceller> */
    private array $cancellers = [];

    /**
     * @param non-empty-string $consumerTag
     */
    public function add(string $consumerTag, Canceller $canceller): void
    {
        $this->cancellers[$consumerTag] = $canceller;
    }

    /**
     * @param non-empty-string $consumerTag
     */
    public function cancel(string $consumerTag, bool $noWait = false, ?\Throwable $error = null): void
    {
        if (isset($this->cancellers[$consumerTag])) {
            $canceller = $this->cancellers[$consumerTag];
            unset($this->cancellers[$consumerTag]);

            $canceller->cancel($noWait, $error);
        }
    }

    public function cancelAll(bool $noWait = false, ?\Throwable $error = null): void
    {
        try {
            foreach ($this->cancellers as $canceller) {
                $canceller->cancel($noWait, $error);
            }
        } finally {
            $this->cancellers = [];
        }
    }
}
