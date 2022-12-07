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
use RedisException;
use support\Container;
use support\Redis;
use Tests\MockClass\MockRedis;
use Workbunny\WebmanPushServer\Events\AbstractEvent;
use Workbunny\WebmanPushServer\Events\Unsubscribe;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Timer;
use Workerman\Worker;

class Server implements ServerInterface
{
    /**
     * @var string $version version
     */
    public static string $version = '0.1.1';

    /**
     * @var bool $debug debug mode
     */
    public static bool $debug = false;

    /**
     * @var AbstractEvent|null 仅用于单元测试
     */
    public static ?AbstractEvent $eventFactory = null;

    /**
     * @var TcpConnection[][][] = [
     *      'appKey_1' => [
     *          'channel_1' => [
     *              'socketId_1' => TcpConnection_1, @see self::_getConnectionProperty()
     *          ],
     *          'channel_2' => [
     *              'socketId_2' => TcpConnection_2, @see self::_getConnectionProperty()
     *              'socketId_3' => TcpConnection_3, @see self::_getConnectionProperty()
     *          ]
     *      ],
     *      'appKey_2' => [
     *         'channel_1' => [
     *             'socketId_4' => TcpConnection_4, @see self::_getConnectionProperty()
     *         ]
     *     ],
     * ]
     */
    protected array $_connections = [];

    /**
     * channel信息
     * app_{appKey1}:channel_{channel1}:info = [
     *      type               => 'presence', // 通道类型
     *      subscription_count => 0,          // 订阅数
     *      user_count         => 0,          // 用户数
     * ]
     *
     * user信息
     * app_{appKey1}:channel_{channel1}:uid_{uid1} = [
     *      user_info  => json string,  // 用户信息json
     *      socket_id  => socketId      // 客户端id
     * ]
     *
     * @var \Redis|null 储存
     */
    protected static ?\Redis $_storage = null;

    /** @var Server|null  */
    protected static ?Server $_server = null;

    /** @var int|null 心跳定时器 */
    protected ?int $_heartbeatTimer = null;

    /** @var int 心跳 */
    protected int $_keepaliveTimeout = 60;

    /**
     * @desc debug模式下打印字符串 "{$service->name} listen: $listen" . PHP_EOL"
     * @param array $services = [
     *      class_name => [
     *          'handler'     => class_name,
     *          'listen'      => host
     *          'context'     => [],
     *          'constructor' => []
     *      ]
     * ]
     * @throws Exception
     */
    public function __construct(array $services)
    {
        // init service
        foreach ($services as $service){
            $handler = self::isDebug() ?
                new $service['handler']($services['constructor'] ?? []) :
                Container::make($service['handler'], $services['constructor'] ?? []);
            $listen  = $service['listen'] ?? '';
            $context = $service['context'] ?? [];
            if($handler instanceof ServerInterface){
                $service = new Worker($listen, $context);
                foreach ([
                             'onConnect',
                             'onMessage',
                             'onClose',
                             'onWorkerStart',
                             'onWorkerStop'
                         ] as $property) {
                    if(method_exists($handler, $property)){
                        $service->$property = [$handler, $property];
                    }
                }
                $service->reusePort = true;
                $service->name = 'workbunny/webman-push-server/api-service';
                if($listen) {
                    echo "{$service->name} listen: $listen" . PHP_EOL;
                    if(!self::isDebug()){
                        $service->listen();
                    }
                }
            }
        }
    }

    /**
     * @return bool
     */
    public static function isDebug(): bool
    {
        return self::$debug;
    }

    /**
     * @return Server|null
     */
    public static function getServer(): ?Server
    {
        return self::$_server;
    }

    /** @inheritDoc */
    public static function getConfig(string $key, $default = null)
    {
        return self::isDebug() ?
            config('plugin.workbunny.webman-push-server.app.push-server.' . $key, $default) :
            \config('plugin.workbunny.webman-push-server.app.push-server.' . $key, $default);
    }

    /** @inheritDoc */
    public static function getStorage(): \Redis
    {
        if(!self::$_storage instanceof \Redis){
            self::$_storage = self::isDebug() ?
                new MockRedis() :
                Redis::connection(self::getConfig('redis_channel', 'default'))->client();
        }
        return self::$_storage;
    }

    /**
     * 发布事件
     * @desc debug模式下会输出字符串 publishToClients
     * @param string $appKey
     * @param string $channel
     * @param string $event
     * @param mixed $data
     * @param string|null $socketId
     * @return void
     */
    public function publishToClients(string $appKey, string $channel, string $event, $data, ?string $socketId = null)
    {
        if (!isset($this->_connections[$appKey][$channel])) {
            return;
        }
        foreach ($this->_connections[$appKey][$channel] as $connection) {
            if($this->_getConnectionProperty($connection, 'socketId') === $socketId){
                continue;
            }
            $this->_setConnectionProperty($connection, 'clientNotSendPingCount', 0);
            $this->send($connection, $channel, $event, $data);
        }
    }

    /**
     * @param TcpConnection $connection
     * @param string|null $code
     * @param string|null $message
     * @param array $extra
     * @return void
     */
    public function error(TcpConnection $connection, ?string $code, ?string $message = null, array $extra = []): void
    {
        $this->send($connection, null, EVENT_ERROR, array_merge([
            'code'    => $code,
            'message' => $message
        ], $extra));
    }

    /**
     * @param TcpConnection $connection
     * @param string|null $channel
     * @param string|null $event
     * @param mixed|null $data
     * @return void
     */
    public function send(TcpConnection $connection, ?string $channel, ?string $event, $data): void
    {
        $response = [];
        if($channel){
            $response['channel'] = $channel;
        }
        if($event){
            $response['event'] = $event;
        }
        if($data){
            $response['data'] = $data;
        }
        $connection->send($response ? json_encode($response, JSON_UNESCAPED_UNICODE) : '{}');

        if($event){
            if(AbstractEvent::pre($event) === AbstractEvent::SERVER_EVENT) {
                try {
                    HookServer::publish( PUSH_SERVER_EVENT_SERVER_EVENT, array_merge($response, [
                        'id'      => uuid(),
                        'time_ms' => microtime(true)
                    ]));
                }catch (RedisException $exception){
                    error_log($exception->getMessage() . PHP_EOL);
                }
            }
        }
    }

    /**
     * 终止连接
     * @param string $appKey
     * @param string $socketId
     * @param array $data
     * @return void
     */
    public function terminateConnections(string $appKey, string $socketId, array $data): void
    {
        $channelConnections = $this->_connections[$appKey];
        foreach ($channelConnections as $channel => $channelConnection){
            if(isset($channelConnection[$socketId])){
                $connection = $channelConnection[$socketId];
                $this->send($connection, $channel, EVENT_TERMINATE_CONNECTION, $data);
                $this->onClose($connection);
                $connection->destroy();
            }
        }
    }

    /**
     * 创建一个全局的客户端id
     * @return string
     */
    protected function _createSocketId(): string
    {
        return uuid();
    }

    /**
     * 获得channel类型
     * @param string $channel
     * @return string
     */
    public function _getChannelType(string $channel): string
    {
        return (strpos($channel, 'private-') === 0)
            ? CHANNEL_TYPE_PRIVATE
            : ((strpos($channel, 'presence-') === 0) ? CHANNEL_TYPE_PRESENCE : CHANNEL_TYPE_PUBLIC);
    }

    /**
     * @param TcpConnection $connection
     * @param string $appKey
     * @param string $channel
     * @return void
     */
    public function _setConnection(TcpConnection $connection, string $appKey, string $channel): void
    {
        $this->_connections[$appKey][$channel][$this->_getConnectionProperty($connection, 'socketId')] = $connection;
    }

    /**
     * @param TcpConnection $connection
     * @param string $appKey
     * @param string $channel
     * @return TcpConnection|null
     */
    public function _getConnection(TcpConnection $connection, string $appKey, string $channel): ?TcpConnection
    {
        return $this->_connections[$appKey][$channel][$this->_getConnectionProperty($connection, 'socketId')] ?? null;
    }

    /**
     * @param TcpConnection $connection
     * @param string $appKey
     * @param string $channel
     * @return void
     */
    public function _unsetConnection(TcpConnection $connection, string $appKey, string $channel): void
    {
        unset($this->_connections[$appKey][$channel][$this->_getConnectionProperty($connection, 'socketId')]);
    }

    /**
     * @param TcpConnection $connection
     * @param string $property = clientNotSendPingCount (int) | appKey (string) | queryString (string) | socketId (string) | channels = [ channel => ''|uid]
     * @param mixed|null $value
     * @return void
     */
    public function _setConnectionProperty(TcpConnection $connection, string $property, $value): void
    {
        $connection->$property = $value;
    }

    /**
     * @param TcpConnection $connection
     * @param string $property = clientNotSendPingCount (int) | appKey (string) | queryString (string) | socketId (string) | channels = [ channel => ''|uid]
     * @param mixed|null $default
     * @return mixed|null
     */
    public function _getConnectionProperty(TcpConnection $connection, string $property, $default = null)
    {
        return $connection->$property ?? $default;
    }

    /**
     * @param string $appKey
     * @param string $channel
     * @return array[]
     * @throws RedisException
     */
    public function _getPresenceChannelDataForSubscribe(string $appKey, string $channel): array
    {
        $hash = [];
        while(
            false !== ($keys = self::getStorage()->scan($iterator, $this->_getUserStorageKey($appKey, $channel),100))
        ) {
            foreach($keys as $key) {
                $result = self::getStorage()->hGetAll($key);
                $hash[$result['uid']] = json_decode($result['user_info'], true);
            }
        }
        return [
            CHANNEL_TYPE_PRESENCE => [
                'count' => count($hash),
                'ids'   => array_keys($hash),
                'hash'  => $hash
            ]
        ];
    }

    /**
     * 获取通道储存key
     * @param string $appKey
     * @param string|null $channel
     * @return string
     */
    public function _getChannelStorageKey(string $appKey, ?string $channel = null): string
    {
        $channel = $channel !== null ? $channel : '*';
        return "workbunny:webman-push-server:appKey_$appKey:channel_$channel:info";
    }

    /**
     * 获取通道名称
     * @param string $channelStorageKey
     * @return string
     */
    public function _getChannelName(string $channelStorageKey): string
    {
        $channelKey = explode(':', $channelStorageKey, 5)[3];
        return explode('_', $channelKey, 2)[1];
    }

    /**
     * 获取用户储存key
     * @param string $appKey
     * @param string|null $channel
     * @param string|null $uid
     * @return string
     */
    public function _getUserStorageKey(string $appKey, ?string $channel = null, ?string $uid = null): string
    {
        $channel = $channel !== null ? $channel : '*';
        $uid = $uid !== null ? $uid : '*';
        return "workbunny:webman-push-server:appKey_$appKey:channel_$channel:uid_$uid";
    }

    /**
     * 获取用户id
     * @param string $userStorageKey
     * @return string
     */
    public function _getUserId(string $userStorageKey): string
    {
        $userIdKey = explode(':', $userStorageKey, 5)[4];
        return explode('_', $userIdKey, 2)[1];
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function onWorkerStart(Worker $worker): void
    {
        self::$_server = $this;
        // 心跳检查
        $this->_heartbeatTimer = Timer::add($this->_keepaliveTimeout / 2, function (){
            foreach ($this->_connections as $appKeyConnections) {
                foreach ($appKeyConnections as $channelConnections){
                    foreach ($channelConnections as $connection){
                        if (($count = $this->_getConnectionProperty($connection, 'clientNotSendPingCount')) > 1) {
                            $connection->destroy();
                        }
                        $this->_setConnectionProperty($connection, 'clientNotSendPingCount', $count + 1);
                    }
                }
            }
        });
    }

    /** @inheritDoc */
    public function onWorkerStop(Worker $worker): void{
        if($this->_heartbeatTimer !== null){
            Timer::del($this->_heartbeatTimer);
            $this->_heartbeatTimer = null;
        }
        try {
            self::getStorage()->close();
            self::$_storage = null;
        }catch (RedisException $exception){}
    }

    /** @inheritDoc */
    public function onConnect(TcpConnection $connection): void
    {
        // 设置websocket握手事件回调
        $this->_setConnectionProperty($connection, 'onWebSocketConnect', function(TcpConnection $connection, string $header) {
            $request = new Request($header);
            // 客户端有多少次没在规定时间发送心跳
            $this->_setConnectionProperty($connection, 'clientNotSendPingCount', 0);
            if (!preg_match('/\/app\/([^\/^\?^]+)/', $request->path() ?? '', $match)) {
                $this->error($connection, null, 'Invalid app');
                $connection->pauseRecv();
                return;
            }
            if(!self::getConfig('apps_query')($appKey = $match[1])){
                $this->error($connection, null, "Invalid app_key");
                $connection->pauseRecv();
                return;
            }

            $this->_setConnectionProperty($connection, 'clientNotSendPingCount', 0);
            $this->_setConnectionProperty($connection, 'appKey', $appKey);
            $this->_setConnectionProperty($connection, 'queryString', $request->queryString() ?? '');
            $this->_setConnectionProperty($connection, 'socketId', $socketId = $this->_createSocketId());
            $this->_setConnectionProperty($connection, 'channels', ['' => '']);
            $this->_setConnection($connection, $appKey, '');

            /**
             * 向客户端发送链接成功的消息
             * {"event":"pusher:connection_established","data":"{"socket_id":"208836.27464492","activity_timeout":120}"}
             */
            $this->send($connection, null, EVENT_CONNECTION_ESTABLISHED, [
                'socket_id'        => $socketId,
                'activity_timeout' => 55
            ]);
        });
    }

    /** @inheritDoc */
    public function onMessage(TcpConnection $connection, $data): void
    {
        if(is_string($data)){
            $this->_setConnectionProperty($connection, 'clientNotSendPingCount', 0);
            if(!$data = json_decode($data, true)){
                return;
            }

            self::$eventFactory = null;
            if(self::$eventFactory = $factory = AbstractEvent::factory($data['event'])){
                $factory->response($this, $connection, $data);
                return;
            }
            $this->error($connection,null, 'Client event rejected - Unknown event');
        }
    }

    /** @inheritDoc */
    public function onClose(TcpConnection $connection): void
    {
        if(!$socketId = $this->_getConnectionProperty($connection, 'socketId')){
            return;
        }
        unset($this->_connections[$appKey = $this->_getConnectionProperty($connection, 'appKey')][''][$socketId]);
        if($channels = $this->_getConnectionProperty($connection, 'channels', [])){
            foreach ($channels as $channel => $value) {
                if ('' === $channel) {
                    continue;
                }
                switch ($value){
                    case CHANNEL_TYPE_PRIVATE:
                    case CHANNEL_TYPE_PUBLIC:
                        $userId = null;
                        $type = $value;
                        break;
                    default:
                        $userId = $value;
                        $type = CHANNEL_TYPE_PRESENCE;
                        break;
                }
                Unsubscribe::unsubscribeChannel($this, $connection, $channel, $type, $userId);
                unset($this->_connections[$appKey][$channel][$socketId]);
            }
        }
    }
}

