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

use support\Log;
use Workbunny\WebmanPushServer\Events\AbstractEvent;
use Workbunny\WebmanPushServer\Events\Unsubscribe;
use Workbunny\WebmanPushServer\Traits\ChannelMethods;
use Workbunny\WebmanPushServer\Traits\HelperMethods;
use Workbunny\WebmanPushServer\Traits\StorageMethods;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Timer;

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
     *      'socketId_1' => TcpConnection_1, @see self::getConnectionProperty()
     *      'socketId_2' => TcpConnection_2, @see self::getConnectionProperty()
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
    protected int $_keepaliveTimeout;

    /** @var AbstractEvent|null 最近一个事件 */
    protected ?AbstractEvent $_lastEvent = null;

    public function __construct()
    {
        $this->_keepaliveTimeout = (int)self::getConfig('heartbeat', 60);
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
        if ($this->getKeepaliveTimeout() > 0 and !$this->getHeartbeatTimer()) {
            $this->setHeartbeatTimer(Timer::add(
                round($this->getKeepaliveTimeout() / 2, 2),
                [static::class, '_heartbeatChecker']
            ));
        }
    }

    /**
     * @return void
     */
    public function onWorkerStop(): void{
        if ($this->getHeartbeatTimer()){
            Timer::del($this->getHeartbeatTimer());
            $this->setHeartbeatTimer(null);
        }
        static::channelClose();
    }

    /**
     * @param TcpConnection $connection
     * @return void
     */
    public function onConnect(TcpConnection $connection): void
    {
        // 为TcpConnection object设置属性
        static::setConnectionProperty($connection, 'appKey', static::$unknownTag);
        static::setConnectionProperty($connection, 'clientNotSendPingCount', 0);
        static::setConnectionProperty($connection, 'socketId', $socketId = static::createSocketId());
        // 设置websocket握手事件回调
        static::setConnectionProperty($connection, 'onWebSocketConnect',
            // ws 连接会调用该回调
            function (TcpConnection $connection, string $header) use ($socketId) {
                $request = new Request($header);
                if (!preg_match('/\/app\/([^\/^\?^]+)/', $request->path() ?? '', $match)) {
                    static::error($connection, null, 'Invalid app', true);
                    return;
                }
                // 默认在空字符串域
                $appKey = '';
                // 获取app验证回调，如果没有验证回调则忽略验证
                if ($appVerifyCallback = static::getConfig('app_verify', getBase: true)) {
                    if (!call_user_func($appVerifyCallback, $appKey = $match[1])) {
                        static::error($connection, null, "Invalid app_key", true);
                        return;
                    }
                }
                // 设置push client connection属性
                static::setConnectionProperty($connection, 'appKey', $appKey);
                static::setConnectionProperty($connection, 'queryString', $request->queryString() ?? '');
                static::setConnectionProperty($connection, 'channels', []);
                // 移除unknown连接中对应的socketId
                static::unsetConnection(static::$unknownTag, $socketId);
                // 设置appKey连接
                static::setConnection($appKey, $socketId, $connection);
                /**
                 * 向客户端发送链接成功的消息
                 * {"event":"pusher:connection_established","data":"{"socket_id":"208836.27464492","activity_timeout":120}"}
                 */
                static::send($connection, null, EVENT_CONNECTION_ESTABLISHED, [
                    'socket_id' => $socketId,
                    'activity_timeout' => $this->_keepaliveTimeout - 5
                ]);
            });
        // 设置unknown连接, 交由心跳回收
        static::setConnection(static::$unknownTag, $socketId, $connection);
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
                    static::setConnectionProperty($connection, 'clientNotSendPingCount', 0);
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
            $socketId = static::getConnectionProperty($connection, 'socketId') and
            $appKey = static::getConnectionProperty($connection, 'appKey')
        ) {
            // 退订频道
            if ($channels = static::getConnectionProperty($connection, 'channels', [])) {
                foreach ($channels as $channel => $type) {
                    // 退订事件
                    Unsubscribe::unsubscribeChannel($connection, $channel);
                    // 移除通道
                    static::unsetChannels($appKey, $channel, $socketId);
                }
            }
            // 移除连接
            static::unsetConnection($appKey, $socketId);
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
     * @return int|null
     */
    public function getHeartbeatTimer(): ?int
    {
        return $this->_heartbeatTimer;
    }

    /**
     * @param int|null $heartbeatTimer
     * @return void
     */
    public function setHeartbeatTimer(?int $heartbeatTimer): void
    {
        $this->_heartbeatTimer = $heartbeatTimer;
    }

    /**
     * @return int
     */
    public function getKeepaliveTimeout(): int
    {
        return $this->_keepaliveTimeout;
    }

    /**
     * @param int $keepaliveTimeout
     * @return void
     */
    public function setKeepaliveTimeout(int $keepaliveTimeout): void
    {
        $this->_keepaliveTimeout = $keepaliveTimeout;
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
                    static::unsetConnection(
                        static::getConnectionProperty($connection, 'appKey'),
                        static::getConnectionProperty($connection, 'socketId')
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
        $response = static::filter([
            'timestamp' => intval(microtime(true) * 1000),
            'channel'   => $channel,
            'event'     => $event,
            'data'      => $data
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

    /**
     * 创建一个全局的客户端id
     *
     * @return string
     */
    public static function createSocketId(): string
    {
        return uuid();
    }

    /**
     * 获得channel类型
     *
     * @param string $channel
     * @return string
     */
    public static function getChannelType(string $channel): string
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
    public static function setConnectionProperty(TcpConnection $connection, string $property, mixed $value): void
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
    public static function getConnectionProperty(TcpConnection $connection, string $property, mixed $default = null): mixed
    {
        return $connection->$property ?? $default;
    }

    /**
     * @return TcpConnection[][]
     */
    public static function getConnections(): array
    {
        return static::$_connections;
    }

    /**
     * @param array $connections
     * @return void
     */
    public static function setConnections(array $connections): void
    {
        static::$_connections = $connections;
    }

    /**
     * 设置连接
     *
     * @param string $appKey
     * @param string $socketId
     * @param TcpConnection $connection
     * @return void
     */
    public static function setConnection(string $appKey, string $socketId, TcpConnection $connection): void
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
    public static function getConnection(string $appKey, string $socketId): ?TcpConnection
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
    public static function unsetConnection(string $appKey, string $socketId): void
    {
        // 移除connections
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
    public static function setChannel(string $appKey, string $channel, string $socketId): void
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
    public static function getChannels(string $appKey, string $channel, ?string $socketId = null): string|array|null
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
    public static function unsetChannels(string $appKey, string $channel, ?string $socketId = null): void
    {
        if ($socketId !== null) {
            unset(static::$_channels[$appKey][$channel][$socketId]);
            return;
        }
        unset(static::$_channels[$appKey][$channel]);
    }

    /** @inheritDoc */
    public static function _subscribeResponse(string $type, array $data): void
    {
        try {
            // 客户端事件
            if ($type === static::$publishTypeClient) {
                static::verify($data, [
                    ['appKey', 'is_string', true],
                    ['channel', 'is_string', true],
                    ['event', 'is_string', true],
                    ['socketId', 'is_string', false]
                ]);
                // 查询通道下的所有socketId
                $socketIds = static::getChannels($appKey = $data['appKey'], $data['channel']);
                // 发送至socketId对应的连接
                foreach ($socketIds as $socketId) {
                    // 如果存在socketId字段，则是需要做忽略发送
                    if ($socketId !== ($data['socketId'] ?? null)) {
                        // 获取对应connection对象
                        if ($connection = static::getConnection($appKey, $socketId)) {
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
            }
            // 服务事件
            if ($type === static::$publishTypeServer) {
                static::verify($data, [
                    ['appKey', 'is_string', true],
                    ['event', 'is_string', true],
                    ['socketId', 'is_string', false],
                ]);
                // 断开连接事件
                if (
                    ($socketId = $data['socketId'] ?? null) and
                    $data['event'] === EVENT_TERMINATE_CONNECTION
                ) {
                    static::terminateConnections($data['appKey'], $socketId, $data['data'] ?? []);
                }
            }
        } catch (\InvalidArgumentException $exception) {
            Log::channel('plugin.workbunny.webman-push-server.warning')
                ->warning("[PUSH-SERVER] {$exception->getMessage()}", [
                    'args' => func_get_args(),
                    'method' => __METHOD__
                ]);
        }
    }

    /**
     * @return void
     */
    public static function _heartbeatChecker(): void
    {
        /**
         * @var string $appKey
         * @var array $connections
         */
        foreach (static::getConnections() as $appKey => $connections) {
            /**
             * @var string $socketId
             * @var TcpConnection $connection
             */
            foreach ($connections as $socketId => $connection) {
                $count = static::getConnectionProperty($connection, 'clientNotSendPingCount');
                if ($count === null or $count > 1) {
                    static::terminateConnections($appKey, $socketId, [
                        'type'      => 'heartbeat',
                        'message'   => 'Terminate connection by heartbeat'
                    ]);
                    continue;
                }
                static::setConnectionProperty($connection, 'clientNotSendPingCount', $count + 1);
            }
        }
    }
}