<?php
declare(strict_types=1);

namespace Tests\MockClass;

use Workbunny\WebmanPushServer\PushServer;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Response;

class MockTcpConnection extends TcpConnection
{

    protected Response|string|null $_sendBuffer = null;

    protected Response|string|null $_recvBuffer = null;

    public function __construct($remote_address = '')
    {
        ++self::$statistics['connection_count'];
        $this->id = $this->realId = self::$idRecorder++;
        if (self::$idRecorder === PHP_INT_MAX) {
            self::$idRecorder = 0;
        }
        $this->maxSendBufferSize        = self::$defaultMaxSendBufferSize;
        $this->maxPackageSize           = self::$defaultMaxPackageSize;
        $this->_remoteAddress           = $remote_address;
        static::$connections[$this->id] = $this;
    }

    /**
     * @return bool
     */
    public function isPaused(): bool
    {
        return $this->isPaused;
    }

    /**
     * @return string|Response|null
     */
    public function getSendBuffer()
    {
        return $this->_sendBuffer;
    }

    /**
     * @param string|Response|null $sendBuffer
     */
    public function setSendBuffer(Response|string|null $sendBuffer): void
    {
        $this->_sendBuffer = $sendBuffer;
    }

    /**
     * @return Response|string|null
     */
    public function getRecvBuffer(): Response|string|null
    {
        return $this->_recvBuffer;
    }

    /**
     * @param Response|string|null $recvBuffer
     */
    public function setRecvBuffer(Response|string|null $recvBuffer): void
    {
        $this->_recvBuffer = $recvBuffer;
    }

    /**
     * @param mixed $sendBuffer
     * @param mixed $raw
     * @return bool|null
     */
    public function send(mixed $sendBuffer, bool $raw = false): ?bool
    {
        $this->setSendBuffer($sendBuffer);
        return true;
    }

    /**
     * @param mixed $data
     * @param mixed $raw
     */
    public function close(mixed $data = null, bool $raw = false): void
    {
        if ($data) {
            $this->send($data, $raw);
        }
        (new PushServer())->onClose($this);
    }

    public function pauseRecv(): void
    {
        $this->isPaused = true;
    }
}