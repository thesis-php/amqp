<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Protocol;

/**
 * @internal
 */
final readonly class ClassMethod
{
    public const int CONNECTION_START = 10;
    public const int CONNECTION_START_OK = 11;
    public const int CONNECTION_SECURE = 20;
    public const int CONNECTION_SECURE_OK = 21;
    public const int CONNECTION_TUNE = 30;
    public const int CONNECTION_TUNE_OK = 31;
    public const int CONNECTION_OPEN = 40;
    public const int CONNECTION_OPEN_OK = 41;
    public const int CONNECTION_CLOSE = 50;
    public const int CONNECTION_CLOSE_OK = 51;
    public const int CONNECTION_BLOCKED = 60;
    public const int CONNECTION_UNBLOCKED = 61;
    public const int CHANNEL_OPEN = 10;
    public const int CHANNEL_OPEN_OK = 11;
    public const int CHANNEL_FLOW = 20;
    public const int CHANNEL_FLOW_OK = 21;
    public const int CHANNEL_CLOSE = 40;
    public const int CHANNEL_CLOSE_OK = 41;
    public const int ACCESS_REQUEST = 10;
    public const int ACCESS_REQUEST_OK = 11;
    public const int EXCHANGE_DECLARE = 10;
    public const int EXCHANGE_DECLARE_OK = 11;
    public const int EXCHANGE_DELETE = 20;
    public const int EXCHANGE_DELETE_OK = 21;
    public const int EXCHANGE_BIND = 30;
    public const int EXCHANGE_BIND_OK = 31;
    public const int EXCHANGE_UNBIND = 40;
    public const int EXCHANGE_UNBIND_OK = 51;
    public const int QUEUE_DECLARE = 10;
    public const int QUEUE_DECLARE_OK = 11;
    public const int QUEUE_BIND = 20;
    public const int QUEUE_BIND_OK = 21;
    public const int QUEUE_PURGE = 30;
    public const int QUEUE_PURGE_OK = 31;
    public const int QUEUE_DELETE = 40;
    public const int QUEUE_DELETE_OK = 41;
    public const int QUEUE_UNBIND = 50;
    public const int QUEUE_UNBIND_OK = 51;
    public const int BASIC_QOS = 10;
    public const int BASIC_QOS_OK = 11;
    public const int BASIC_CONSUME = 20;
    public const int BASIC_CONSUME_OK = 21;
    public const int BASIC_CANCEL = 30;
    public const int BASIC_CANCEL_OK = 31;
    public const int BASIC_PUBLISH = 40;
    public const int BASIC_RETURN = 50;
    public const int BASIC_DELIVER = 60;
    public const int BASIC_GET = 70;
    public const int BASIC_GET_OK = 71;
    public const int BASIC_GET_EMPTY = 72;
    public const int BASIC_ACK = 80;
    public const int BASIC_REJECT = 90;
    public const int BASIC_RECOVER_ASYNC = 100;
    public const int BASIC_RECOVER = 110;
    public const int BASIC_RECOVER_OK = 111;
    public const int BASIC_NACK = 120;
    public const int TX_SELECT = 10;
    public const int TX_SELECT_OK = 11;
    public const int TX_COMMIT = 20;
    public const int TX_COMMIT_OK = 21;
    public const int TX_ROLLBACK = 30;
    public const int TX_ROLLBACK_OK = 31;
    public const int CONFIRM_SELECT = 10;
    public const int CONFIRM_SELECT_OK = 11;

    private function __construct() {}
}
