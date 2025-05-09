<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Protocol;

/**
 * @internal
 */
final readonly class ClassType
{
    public const int CONNECTION = 10;
    public const int CHANNEL = 20;
    public const int ACCESS = 30;
    public const int EXCHANGE = 40;
    public const int QUEUE = 50;
    public const int BASIC = 60;
    public const int TX = 90;
    public const int CONFIRM = 85;

    private function __construct() {}
}
