<?php
declare(strict_types=1);

namespace Tests\MockClass;

use Workbunny\WebmanPushServer\PushServer;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Response;

class MockTcpConnection extends TcpConnection
{

    public function __construct($remote_address = '')
    {
        ++self::$statistics['connection_count'];
        $this->id = $this->_id = self::$_idRecorder++;
        if(self::$_idRecorder === \PHP_INT_MAX){
            self::$_idRecorder = 0;
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
        return $this->_isPaused;
    }

    /**
     * @return string|Response
     */
    public function getSendBuffer()
    {
        return $this->_sendBuffer;
    }

    /**
     * @param string|Response $sendBuffer
     */
    public function setSendBuffer($sendBuffer): void
    {
        $this->_sendBuffer = $sendBuffer;
    }

    /**
     * @return string
     */
    public function getRecvBuffer(): string
    {
        return $this->_recvBuffer;
    }

    /**
     * @param string $recvBuffer
     */
    public function setRecvBuffer(string $recvBuffer): void
    {
        $this->_recvBuffer = $recvBuffer;
    }

    /**
     * @param mixed $send_buffer
     * @param mixed $raw
     */
    public function send($send_buffer, $raw = false)
    {
        $this->setSendBuffer($send_buffer);
    }

    /**
     * @param mixed $data
     * @param mixed $raw
     */
    public function close($data = null, $raw = false)
    {
        if ($data) {
            $this->send($data, $raw);
        }
        (new PushServer())->onClose($this);
    }

    /**
     * @return void
     */
    public function pauseRecv()
    {
        $this->_isPaused = true;
    }
}