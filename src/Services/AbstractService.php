<?php
declare(strict_types=1);

namespace Workbunny\WebmanPushServer\Services;

use Workbunny\WebmanPushServer\Server;
use Workerman\Connection\TcpConnection;
use Workerman\Worker;

abstract class AbstractService extends Worker
{
    /** @var Server  */
    protected Server $_server;

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
     * @return Server
     */
    public function getServer(): Server
    {
        return $this->_server;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->getConfig('name', 'unknown');
    }

    /**
     * @param string $key
     * @param $default
     * @return mixed|null
     */
    public function getExtra(string $key, $default = null)
    {
        return $this->getConfig('extra', [])[$key] ?? $default;
    }

    /**
     * @param string $key
     * @param $value
     * @return void
     */
    public function setExtra(string $key, $value): void
    {
        $this->_config['extra'][$key] = $value;
    }

    /**
     * @param Server $server
     * @param array $config = [
     *      'name'           => SERVICE_NAME,
     *      'count'          => SERVICE_COUNT,
     *      'socket_name'    => PROTOCOL://HOST:PORT
     *      'context_option' => CONTEXT_OPTION_MAP,
     *      'extra'          => EXTRA_MAP
     * ]
     */
    public function __construct(Server $server, array $config)
    {
        $this->_config = $config;
        $this->_server = $server;

        $this->name = 'plugin.workbunny.webman-push-server.' . $this->getName();
        $this->count = $this->getConfig('count', 1);
        $this->onWorkerStart = [$this, 'onWorkerStart'];
        $this->onWorkerStop  = [$this, 'onWorkerStop'];
        $this->onConnect     = [$this, 'onConnect'];
        $this->onClose       = [$this, 'onClose'];
        $this->onMessage     = [$this, 'onMessage'];

        parent::__construct($this->getConfig('socket_name', ''), $this->getConfig('context_option', []));
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