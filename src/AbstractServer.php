<?php
declare(strict_types=1);

namespace Workbunny\WebmanPushServer;

use Workerman\Connection\TcpConnection;
use Workerman\Worker;

abstract class AbstractServer
{
    /** @var array  */
    protected array $_config = [];

    /**
     * @param string|null $key
     * @param $default
     * @return array|mixed|null
     */
    public function getConfig(?string $key = null, $default = null)
    {
        return $key === null ? $this->_config : ($this->_config[$key] ?? $default);
    }

    /**
     * @param array|null $config
     */
    public function __construct(?array $config = null)
    {
        $this->_config = $config ?? $this->_config;
    }

    /**
     * 服务启动
     * @see Worker::$onWorkerStart
     * @param Worker $worker
     * @return void
     */
    abstract public function onWorkerStart(Worker $worker): void;

    /**
     * 服务停止
     * @see Worker::$onWorkerStop
     * @param Worker $worker
     * @return void
     */
    abstract public function onWorkerStop(Worker $worker): void;

    /**
     * 连接事件
     * @see Worker::$onConnect
     * @param TcpConnection $connection
     * @return void
     */
    abstract public function onConnect(TcpConnection $connection): void;

    /**
     * 连接关闭事件
     * @see Worker::$onClose
     * @param TcpConnection $connection
     * @return void
     */
    abstract public function onClose(TcpConnection $connection): void;

    /**
     * 消息事件
     * @see Worker::$onMessage
     * @param TcpConnection $connection
     * @param $data
     * @return void
     */
    abstract public function onMessage(TcpConnection $connection, $data): void;
}