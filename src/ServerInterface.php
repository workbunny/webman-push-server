<?php
/**
 * This file is part of workbunny.
 *
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    chaz6chez<chaz6chez1993@outlook.com>
 * @copyright chaz6chez<chaz6chez1993@outlook.com>
 * @link      https://github.com/workbunny/webman-push-server
 * @license   https://github.com/workbunny/webman-push-server/blob/main/LICENSE
 */
declare(strict_types=1);

namespace Workbunny\WebmanPushServer;

use Workerman\Connection\TcpConnection;
use Workerman\Worker;

interface ServerInterface
{
    /**
     * 获取配置
     * @param string $key
     * @param mixed $default
     * @return array|mixed|null
     */
    public static function getConfig(string $key, $default = null);

    /**
     * 获取储存器
     * @return \Redis
     */
    public static function getStorage(): \Redis;


    /**
     * 服务启动
     * @see Worker::$onWorkerStart
     * @param Worker $worker
     * @return void
     */
    public function onWorkerStart(Worker $worker): void;

    /**
     * 服务停止
     * @see Worker::$onWorkerStop
     * @param Worker $worker
     * @return void
     */
    public function onWorkerStop(Worker $worker): void;

    /**
     * 连接事件
     * @see Worker::$onConnect
     * @param TcpConnection $connection
     * @return void
     */
    public function onConnect(TcpConnection $connection): void;

    /**
     * 连接关闭事件
     * @see Worker::$onClose
     * @param TcpConnection $connection
     * @return void
     */
    public function onClose(TcpConnection $connection): void;

    /**
     * 消息事件
     * @see Worker::$onMessage
     * @param TcpConnection $connection
     * @param $data
     * @return void
     */
    public function onMessage(TcpConnection $connection, $data): void;
}