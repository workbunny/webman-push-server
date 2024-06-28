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

use Exception;
use Workbunny\WebmanPushServer\Events\AbstractEvent;
use Workbunny\WebmanPushServer\Events\Unsubscribe;
use Workbunny\WebmanPushServer\Traits\ChannelMethods;
use Workbunny\WebmanPushServer\Traits\HelperMethods;
use Workbunny\WebmanPushServer\Traits\StorageMethods;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Timer;
use Workerman\Worker;

class PushServer
{
    use HelperMethods;
    use ChannelMethods;
    use StorageMethods;

    /**
     * @var string $version version
     */
    public static string $version = VERSION;

    /**
     * @var string 未知连接储存的appKey标签
     */
    public static string $unknownTag = '<unknown>';

    /**
     * 当前进程所有连接
     *
     * @var TcpConnection[][] = [
     *  'appKey_1' => [
     *      'socketId_1' => TcpConnection_1, @see self::_getConnectionProperty()
     *      'socketId_2' => TcpConnection_2, @see self::_getConnectionProperty()
     *  ],
     * ]
     */
    protected static array $_connections = [];

    /**
     * 当前进程所有通道及所在通道的连接id
     *
     * @var array  = [
     *  'appKey' => [
     *      'channel' => [
     *          'socketId_1' => 'socketId_1',
     *          'socketId_2' => 'socketId_2'
     *      ]
     *  ]
     * ]
     */
    protected static array $_channels = [];

    /** @var int|null 心跳定时器 */
    protected ?int $_heartbeatTimer = null;

    /** @var int 心跳 */
    protected int $_keepaliveTimeout = 60;

    /** @var AbstractEvent|null 最近一个事件 */
    protected ?AbstractEvent $_lastEvent = null;

    public function __construct()
    {
        $this->_keepaliveTimeout = self::getConfig('heartbeat', 60);
    }

    /**
     * 获取配置
     *
     * @param string $key
     * @param mixed|null $default
     * @param bool $getBase
     * @return mixed
     */
    public static function getConfig(string $key, mixed $default = null, bool $getBase = false): mixed
    {
        return \config(
            ($getBase ?
                'plugin.workbunny.webman-push-server.app.' :
                'plugin.workbunny.webman-push-server.app.push-server.') .
            $key, $default
        );
    }

    /**
     * @return void
     */
    public function onWorkerStart(): void
    {
        // 通道订阅
        static::subscribe();
        // 心跳设置
        if ($this->_keepaliveTimeout > 0 and !$this->_heartbeatTimer) {
            $this->_heartbeatTimer = Timer::add(
                round($this->_keepaliveTimeout / 2, 2),
                function () {
                    /**
                     * @var string $appKey
                     * @var array $connections
                     */
                    foreach (static::$_connections as $appKey => $connections) {
                        /**
                         * @var string $socketId
                         * @var TcpConnection $connection
                         */
                        foreach ($connections as $socketId => $connection) {
                            $count = static::_getConnectionProperty($connection, 'clientNotSendPingCount');
                            if ($count === null or $count > 1) {
                                $connection->destroy();
                                static::_unsetConnection($appKey, $socketId);
                                continue;
                            }
                            static::_setConnectionProperty($connection, 'clientNotSendPingCount', $count + 1);
                        }
                    }
                });
        }
    }

    /**
     * @return void
     */
    public function onWorkerStop(): void{
        if ($this->_heartbeatTimer){
            Timer::del($this->_heartbeatTimer);
            $this->_heartbeatTimer = 0;
        }
        static::close();
    }

    /**
     * @param TcpConnection $connection
     * @return void
     */
    public function onConnect(TcpConnection $connection): void
    {
        // 为TcpConnection object设置属性
        static::_setConnectionProperty($connection, 'appKey', $appKey = static::$unknownTag);
        static::_setConnectionProperty($connection, 'clientNotSendPingCount', 0);
        static::_setConnectionProperty($connection, 'socketId', $socketId = static::_createSocketId());
        // 设置websocket握手事件回调
        static::_setConnectionProperty($connection, 'onWebSocketConnect',
            // ws 连接会调用该回调
            function(TcpConnection $connection, string $header) use ($appKey, $socketId) {
                $request = new Request($header);
                if (!preg_match('/\/app\/([^\/^\?^]+)/', $request->path() ?? '', $match)) {
                    static::error($connection, null, 'Invalid app', true);
                    return;
                }
                // 获取app验证回调，如果没有验证回调则忽略验证
                $appKey = '';
                if ($appVerifyCallback = static::getConfig('app_verify', getBase: true)) {
                    if (!call_user_func($appVerifyCallback, $appKey = $match[1])) {
                        static::error($connection, null, "Invalid app_key", true);
                        return;
                    }
                }
                // 设置push client connection属性
                static::_setConnectionProperty($connection, 'appKey', $appKey);
                static::_setConnectionProperty($connection, 'queryString', $request->queryString() ?? '');
                static::_setConnectionProperty($connection, 'channels', []);
                /**
                 * 向客户端发送链接成功的消息
                 * {"event":"pusher:connection_established","data":"{"socket_id":"208836.27464492","activity_timeout":120}"}
                 */
                static::send($connection, null, EVENT_CONNECTION_ESTABLISHED, [
                    'socket_id' => $socketId,
                    'activity_timeout' => $this->_keepaliveTimeout - 5
                ]);
            });
        // 设置连接
        static::_setConnection($appKey, $socketId, $connection);
    }

    /**
     * @param TcpConnection $connection
     * @param $data
     * @return void
     */
    public function onMessage(TcpConnection $connection, $data): void
    {
        if (is_string($data)) {
            if ($data = @json_decode($data, true)) {
                // 获取事件
                $this->setLastEvent(AbstractEvent::factory($data['event'] ?? ''));
                if ($event = $this->getLastEvent()) {
                    // 心跳计数归零
                    static::_setConnectionProperty($connection, 'clientNotSendPingCount', 0);
                    // 事件响应
                    $event->response($connection, $data);
                    return;
                }
            }
            static::error($connection,null, 'Client event rejected - Unknown event');
        }
    }

    /**
     * @param TcpConnection $connection
     * @return void
     */
    public function onClose(TcpConnection $connection): void
    {
        if (
            $socketId = static::_getConnectionProperty($connection, 'socketId') and
            $appKey = static::_getConnectionProperty($connection, 'appKey')
        ) {
            // 退订频道
            if ($channels = static::_getConnectionProperty($connection, 'channels', [])) {
                foreach ($channels as $channel => $type) {
                    // 退订事件
                    Unsubscribe::unsubscribeChannel($connection, $channel);
                    // 移除通道
                    static::_unsetChannels($appKey, $channel, $socketId);
                    // 移除连接
                    static::_unsetConnection($appKey, $socketId);
                }
            }
        }
    }

    /**
     * @return AbstractEvent|null
     */
    public function getLastEvent(): ?AbstractEvent
    {
        return $this->_lastEvent;
    }

    /**
     * @param AbstractEvent|null $lastEvent
     * @return void
     */
    public function setLastEvent(?AbstractEvent $lastEvent): void
    {
        $this->_lastEvent = $lastEvent;
    }

    /**
     * 向连接发送错误消息
     *
     * @param TcpConnection $connection 连接
     * @param string|null $code 错误码
     * @param string|null $message 错误信息
     * @param bool $pauseRecv 暂停接收消息，连接随后会被心跳检测回收
     * @return void
     */
    public static function error(TcpConnection $connection, ?string $code, ?string $message = null, bool $pauseRecv = false): void
    {
        static::send($connection, null, EVENT_ERROR, [
            'code'    => $code,
            'message' => $message
        ]);
        if ($pauseRecv) {
            // 如果没有设置心跳检测，则定时销毁连接
            if (static::getConfig('heartbeat', 0) <= 0) {
                Timer::add(60, function() use ($connection) {
                    $connection->destroy();
                    static::_unsetConnection(
                        static::_getConnectionProperty($connection, 'appKey'),
                        static::_getConnectionProperty($connection, 'socketId')
                    );
                });
            }
            // 交给心跳检测销毁连接
            else {
                $connection->pauseRecv();
            }
        }
    }

    /**
     * 向连接发送消息
     *
     * @param TcpConnection $connection
     * @param string|null $channel
     * @param string|null $event
     * @param mixed|null $data
     * @return void
     */
    public static function send(TcpConnection $connection, ?string $channel, ?string $event, mixed $data): void
    {
        $response = static::staticFilter([
            'channel' => $channel,
            'event'   => $event,
            'data'    => $data
        ]);
        // 向连接发送消息
        $connection->send($response ? json_encode($response, JSON_UNESCAPED_UNICODE) : '{}');
        // 向通道发送一个type=server的消息
        static::publishUseRetry(static::$publishTypeServer, $response);
    }

    /**
     * 终止连接
     *
     * @param string $appKey
     * @param string $socketId
     * @param array $data
     * @return void
     */
    public static function terminateConnections(string $appKey, string $socketId, array $data): void
    {
        if ($connection = static::$_connections[$appKey][$socketId] ?? null) {
            // 发送断开连接信息
            static::send($connection, null, EVENT_TERMINATE_CONNECTION, $data);
            // 触发onClose事件
            $connection->close();
        }
    }

    /** @inheritDoc */
    public static function _subscribeResponse(string $type, array $data): void
    {
        if ($type === ChannelMethods::$publishTypeClient) {
            try {
                static::staticVerify($data, [
                    ['appKey', 'is_string', true],
                    ['channel', 'is_string', true],
                    ['event', 'is_string', true],
                    ['socketId', 'is_string', false]
                ]);
                // 查询通道下的所有socketId
                $socketIds = static::_getChannels($appKey = $data['appKey'], $data['channel']);
                // 发送至socketId对应的连接
                foreach ($socketIds as $socketId) {
                    // 如果存在socketId字段，则是需要做忽略发送
                    if ($socketId !== ($data['socketId'] ?? null)) {
                        // 获取对应connection对象
                        if ($connection = static::_getConnection($appKey, $socketId)) {
                            // 发送
                            static::send(
                                $connection,
                                $data['channel'],
                                $data['event'],
                                $data['data'] ?? '{}'
                            );
                        }
                    }
                }
            } catch (\InvalidArgumentException) {}
        }
    }

    /**
     * 创建一个全局的客户端id
     *
     * @return string
     */
    public static function _createSocketId(): string
    {
        return uuid();
    }

    /**
     * 获得channel类型
     *
     * @param string $channel
     * @return string
     */
    public static function _getChannelType(string $channel): string
    {
        return (str_starts_with($channel, 'private-'))
            ? CHANNEL_TYPE_PRIVATE
            : ((str_starts_with($channel, 'presence-')) ? CHANNEL_TYPE_PRESENCE : CHANNEL_TYPE_PUBLIC);
    }

    /**
     * 设置连接信息
     *
     * @param TcpConnection $connection
     * @param string $property = clientNotSendPingCount (int) | appKey (string) | queryString (string) | socketId (string) | channels = [ channel => ''|uid]
     * @param mixed|null $value
     * @return void
     */
    public static function _setConnectionProperty(TcpConnection $connection, string $property, mixed $value): void
    {
        $connection->$property = $value;
    }

    /**
     * 获取连接信息
     *
     * @param TcpConnection $connection
     * @param string $property = clientNotSendPingCount (int) | appKey (string) | queryString (string) | socketId (string) | channels = [ channel => ''|uid]
     * @param mixed|null $default
     * @return mixed|null
     */
    public static function _getConnectionProperty(TcpConnection $connection, string $property, mixed $default = null): mixed
    {
        return $connection->$property ?? $default;
    }

    /**
     * 设置连接
     *
     * @param string $appKey
     * @param string $socketId
     * @param TcpConnection $connection
     * @return void
     */
    public static function _setConnection(string $appKey, string $socketId, TcpConnection $connection): void
    {
        static::$_connections[$appKey][$socketId] = $connection;
    }

    /**
     * 获取连接
     *
     * @param string $appKey
     * @param string $socketId
     * @return TcpConnection|null
     */
    public static function _getConnection(string $appKey, string $socketId): ?TcpConnection
    {
        return static::$_connections[$appKey][$socketId] ?? null;
    }

    /**
     * 移除连接
     *
     * @param string $appKey
     * @param string $socketId
     * @return void
     */
    public static function _unsetConnection(string $appKey, string $socketId): void
    {
        unset(static::$_connections[$appKey][$socketId]);
    }

    /**
     * 设置通道
     *
     * @param string $appKey
     * @param string $channel
     * @param string $socketId
     * @return void
     */
    public static function _setChannel(string $appKey, string $channel, string $socketId): void
    {
        static::$_channels[$appKey][$channel][$socketId] = $socketId;
    }

    /**
     * 获取通道
     *
     * @param string $appKey
     * @param string $channel
     * @param string|null $socketId
     * @return string|array|null
     */
    public static function _getChannels(string $appKey, string $channel, ?string $socketId = null): string|array|null
    {
        return ($socketId !== null) ?
            (static::$_channels[$appKey][$channel][$socketId] ?? null) :
            (static::$_channels[$appKey][$channel] ?? []);
    }

    /**
     * 移除通道
     *
     * @param string $appKey
     * @param string $channel
     * @param string|null $socketId
     * @return void
     */
    public static function _unsetChannels(string $appKey, string $channel, ?string $socketId = null): void
    {
        if ($socketId !== null) {
            unset(static::$_connections[$appKey][$channel][$socketId]);
            return;
        }
        unset(static::$_connections[$appKey][$channel]);
    }
}