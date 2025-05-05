<?php

declare(strict_types=1);

namespace Thesis\Amqp;

/**
 * @api
 * @template-implements \IteratorAggregate<PublishConfirmation>
 */
final class PublishBatchConfirmation implements \IteratorAggregate
{
    /**
     * @param array<non-negative-int, PublishMessage> $messages
     * @param list<PublishConfirmation> $confirmations
     */
    public function __construct(
        public readonly array $messages,
        public readonly array $confirmations,
    ) {}

    public function await(): PublishBatchConfirmationResult
    {
        $unconfirmed = [];
        $unrouted = [];

        foreach (PublishConfirmation::awaitAll($this->confirmations) as $deliveryTag => $publishResult) {
            if ($publishResult === PublishResult::Unrouted) {
                $unrouted[] = $this->messages[$deliveryTag];
            } elseif ($publishResult !== PublishResult::Acked) {
                $unconfirmed[] = $this->messages[$deliveryTag];
            }
        }

        return new PublishBatchConfirmationResult($unconfirmed, $unrouted);
    }

    public function getIterator(): \Traversable
    {
        yield from $this->confirmations;
    }
}
