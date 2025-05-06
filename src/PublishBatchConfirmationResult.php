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
        if (\count($this->unconfirmed) > 0 || \count($this->unrouted) > 0) {
            throw new \RuntimeException('Failed to publish message.');
        }
    }
}
