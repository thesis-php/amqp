<?php

declare(strict_types=1);

namespace Thesis\Amqp;

/**
 * @api
 */
final readonly class PublishBatchConfirmationResult
{
    /**
     * @param list<PublishMessage> $unconfirmed
     * @param list<PublishMessage> $unrouted
     */
    public function __construct(
        public array $unconfirmed,
        public array $unrouted,
    ) {}

    public function ok(): void
    {
        $failedCount = \count($this->unconfirmed) + \count($this->unrouted);

        if ($failedCount > 0) {
            throw new \RuntimeException(\sprintf(
                'Failed to publish %d message%s.',
                $failedCount,
                $failedCount === 1 ? '' : 's',
            ));
        }
    }
}
