<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Io;

use Amp;
use Amp\Socket\Socket;
use Revolt\EventLoop;
use Thesis\AmpBridge\ReaderWriter as AmpReaderWriter;
use Thesis\Amqp\Exception\UnexpectedFrameReceived;
use Thesis\Amqp\Internal\Hooks;
use Thesis\Amqp\Internal\Protocol;
use Thesis\ByteBuffer\BufferedReaderWriter;
use Thesis\ByteReader\UnexpectedEof;
use Thesis\ByteReaderWriter\ReaderWriter;
use Thesis\ByteWriter\Writer;

/**
 * @internal
 */
final class AmqpConnection implements Writer
{
    private readonly Socket $socket;

    private readonly Protocol\Reader $reader;

    private readonly Buffer $buffer;

    private ?string $heartbeatId = null;

    private float $lastWrite = 0;

    private bool $closed = false;

    public function __construct(Socket $socket)
    {
        $this->buffer = Buffer::empty();
        $this->socket = $socket;
        $this->reader = new Protocol\Reader(
            new ReaderWriter(
                new BufferedReaderWriter(
                    new AmpReaderWriter($socket),
                ),
            ),
        );
    }

    /**
     * @template T of Protocol\Frame
     * @param ?class-string<T> $expects
     * @return ($expects is null ? null : T)
     * @throws \Throwable
     */
    public function rpc(Protocol\Frame $frame, ?string $expects = null): ?Protocol\Frame
    {
        $frame->write($this->buffer);
        $this->buffer->writeTo($this);

        if ($expects === null) {
            return null;
        }

        $response = $this->reader->read();

        if ($response->frame instanceof $expects) {
            return $response->frame;
        }

        throw UnexpectedFrameReceived::forFrame($expects, $response->frame::class);
    }

    /**
     * @param iterable<array-key, Protocol\Frame>|Protocol\Frame $frames
     * @throws \Throwable
     */
    public function writeFrame(iterable|Protocol\Frame $frames): void
    {
        if ($frames instanceof Protocol\Frame) {
            $frames = [$frames];
        }

        foreach ($frames as $frame) {
            $frame->write($this->buffer);
        }

        $this->buffer->writeTo($this);
    }

    public function write(string $bytes): void
    {
        $this->socket->write($bytes);
        $this->lastWrite = Amp\now();
    }

    public function ioLoop(Hooks $hooks): void
    {
        $reader = $this->reader;
        $isClosed = &$this->closed;

        EventLoop::queue(static function () use ($reader, &$isClosed, $hooks): void {
            try {
                while (!$isClosed) {
                    $hooks->emit($reader->read());
                }
            } catch (\Throwable $e) {
                if (!$e instanceof UnexpectedEof) {
                    $hooks->error($e);
                }
            } finally {
                $isClosed = true;
            }

            $hooks->complete();
        });
    }

    /**
     * @param non-negative-int $interval
     */
    public function heartbeat(int $interval): void
    {
        $interval = (int) ($interval / 2);

        $this->heartbeatId = EventLoop::repeat((int) ($interval / 3), function () use ($interval): void {
            if (Amp\now() >= ($this->lastWrite + $interval)) {
                $this->writeFrame(Protocol\Heartbeat::frame);
            }
        });
    }

    public function close(): void
    {
        if (!$this->socket->isClosed()) {
            $this->socket->close();
        }

        if ($this->heartbeatId !== null) {
            EventLoop::cancel($this->heartbeatId);
        }

        $this->closed = true;
    }
}
