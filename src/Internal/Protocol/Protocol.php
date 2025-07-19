<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Protocol;

use Thesis\Amqp\Exception\UnsupportedClassMethod;
use Thesis\Amqp\Internal\Io;

/**
 * @internal
 */
enum Protocol
{
    case amqp091;

    /** @var non-negative-int */
    public const int FRAME_END = 206;

    /** @var array<ClassType::*, array<ClassMethod::*, class-string<Frame>>> */
    private const array METHODS = [
        ClassType::CONNECTION => [
            ClassMethod::CONNECTION_START => Frame\ConnectionStart::class,
            ClassMethod::CONNECTION_TUNE => Frame\ConnectionTune::class,
            ClassMethod::CONNECTION_OPEN_OK => Frame\ConnectionOpenOk::class,
            ClassMethod::CONNECTION_CLOSE => Frame\ConnectionClose::class,
            ClassMethod::CONNECTION_CLOSE_OK => Frame\ConnectionCloseOk::class,
        ],
        ClassType::CHANNEL => [
            ClassMethod::CHANNEL_OPEN_OK => Frame\ChannelOpenOkFrame::class,
            ClassMethod::CHANNEL_CLOSE => Frame\ChannelClose::class,
            ClassMethod::CHANNEL_CLOSE_OK => Frame\ChannelCloseOk::class,
            ClassMethod::CHANNEL_FLOW_OK => Frame\ChannelFlowOk::class,
        ],
        ClassType::EXCHANGE => [
            ClassMethod::EXCHANGE_DECLARE_OK => Frame\ExchangeDeclareOk::class,
            ClassMethod::EXCHANGE_BIND_OK => Frame\ExchangeBindOk::class,
            ClassMethod::EXCHANGE_UNBIND_OK => Frame\ExchangeUnbindOk::class,
            ClassMethod::EXCHANGE_DELETE_OK => Frame\ExchangeDeleteOk::class,
        ],
        ClassType::QUEUE => [
            ClassMethod::QUEUE_DECLARE_OK => Frame\QueueDeclareOk::class,
            ClassMethod::QUEUE_BIND_OK => Frame\QueueBindOk::class,
            ClassMethod::QUEUE_UNBIND_OK => Frame\QueueUnbindOk::class,
            ClassMethod::QUEUE_PURGE_OK => Frame\QueuePurgeOk::class,
            ClassMethod::QUEUE_DELETE_OK => Frame\QueueDeleteOk::class,
        ],
        ClassType::TX => [
            ClassMethod::TX_SELECT_OK => Frame\TxSelectOk::class,
            ClassMethod::TX_COMMIT_OK => Frame\TxCommitOk::class,
            ClassMethod::TX_ROLLBACK_OK => Frame\TxRollbackOk::class,
        ],
        ClassType::CONFIRM => [
            ClassMethod::CONFIRM_SELECT_OK => Frame\ConfirmSelectOk::class,
        ],
        ClassType::BASIC => [
            ClassMethod::BASIC_GET_EMPTY => Frame\BasicGetEmpty::class,
            ClassMethod::BASIC_GET_OK => Frame\BasicGetOk::class,
            ClassMethod::BASIC_RECOVER_OK => Frame\BasicRecoverOk::class,
            ClassMethod::BASIC_QOS_OK => Frame\BasicQosOk::class,
            ClassMethod::BASIC_CONSUME_OK => Frame\BasicConsumeOk::class,
            ClassMethod::BASIC_DELIVER => Frame\BasicDeliver::class,
            ClassMethod::BASIC_CANCEL_OK => Frame\BasicCancelOk::class,
            ClassMethod::BASIC_ACK => Frame\BasicAck::class,
            ClassMethod::BASIC_NACK => Frame\BasicNack::class,
            ClassMethod::BASIC_REJECT => Frame\BasicReject::class,
            ClassMethod::BASIC_RETURN => Frame\BasicReturn::class,
        ],
    ];

    public function parseMethod(Io\ReadBytes $reader): Frame
    {
        $classId = $reader->readUint16();
        $methodId = $reader->readUint16();

        return (self::METHODS[$classId][$methodId] ?? throw UnsupportedClassMethod::forClassMethod($classId, $methodId))::read($reader);
    }

    public function parseHeader(Io\ReadBytes $reader): Frame
    {
        return Frame\ContentHeader::read($reader);
    }

    public function parseBody(Io\ReadBytes $reader): Frame
    {
        return Frame\ContentBody::read($reader);
    }
}
