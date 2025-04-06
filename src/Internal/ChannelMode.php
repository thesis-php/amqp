<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal;

/**
 * @internal
 */
enum ChannelMode
{
    case Regular;
    case Transactional;
    case Confirm;

    public function transactional(): bool
    {
        return $this === self::Transactional;
    }

    public function confirming(): bool
    {
        return $this === self::Confirm;
    }
}
