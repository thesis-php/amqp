<?php

declare(strict_types=1);

namespace Thesis\Amqp;

/**
 * @api
 *
 * @template-implements \IteratorAggregate<PublishConfirmation>
 */
final class PublishBatchConfirmation implements \IteratorAggregate
{
    /**
     * @param array<non-negative-int, PublishMessage> $messages
     * @param list<PublishConfirmation> $confirmations
     */
    public function __construct(
        private readonly array $messages,
        private readonly array $confirmations,
    ) {}

    public function awaitAll(): void
    {
        foreach (PublishConfirmation::awaitAll($this->confirmations) as $publishResult) {
            if ($publishResult !== PublishResult::Acked) {
                throw new \LogicException('Failed to publish message.');
            }
        }
    }

    /**
     * @return list<PublishMessage>
     */
    public function unconfirmed(): array
    {
        $unconfirmed = [];

        foreach (PublishConfirmation::awaitAll($this->confirmations) as $deliveryTag => $publishResult) {
            if ($publishResult !== PublishResult::Acked) {
                $unconfirmed[] = $this->messages[$deliveryTag];
            }
        }

        return $unconfirmed;
    }

    public function getIterator(): \Traversable
    {
        yield from $this->confirmations;
    }
}
