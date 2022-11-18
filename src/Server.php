<?php
declare(strict_types=1);

namespace Workbunny\WebmanPushServer;

use RedisException;
use support\Redis;
use Webman\Config;
use Workbunny\WebmanPushServer\Events\AbstractEvent;
use Workbunny\WebmanPushServer\Events\Unsubscribe;
use Workerman\Connection\TcpConnection;
use Workerman\Timer;
use Workerman\Worker;

class Server extends AbstractServer
{
    /** @var Server  */
    protected static Server $_server;

    /**
     * @var TcpConnection[] = [
     *      'appKey_1' => [
     *          'channel_1' => [
     *              'socketId_1' => TcpConnection_1
     *          ],
     *          'channel_2' => [
     *              'socketId_2' => TcpConnection_2,
     *              'socketId_3' => TcpConnection_3
     *          ]
     *      ],
     *      'appKey_2' => [
     *         'channel_1' => [
     *             'socketId_4' => TcpConnection_4
     *         ]
     *     ],
     * ]
     */
    protected array $_connections = [];

    /**
     * app_{appKey1}:channel_{channel1}:info => [
     *      type => 'presence',
     *      subscription_count => 0
     * ]
     *
     * 用户 hash，
     * app_{appKey1}:channel_{channel1}:uid_{uid1} = [
     *      ref_count => 0,
     *      user_info => json string,
     *      socket_id => socketId
     * ]
     *
     * @var \Redis|null 储存
     */
    protected ?\Redis $_storage = null;

    /** @var int|null 心跳定时器 */
    protected ?int $_heartbeatTimer = null;

    /** @var int 心跳 */
    protected int $_keepaliveTimeout = 60;

    /**
     * 构造函数
     * @param array|null $config
     */
    public function __construct(?array $config = null)
    {
        parent::__construct($config);
        self::$_server = $this;
    }

    /**
     * @return Server
     */
    public static function getServer(): Server
    {
        return self::$_server;
    }

    /**
     * 频道 hash
     * @see Server::$_storage
     * @return \Redis|null
     */
    public function getStorage(): ?\Redis
    {
        return $this->_storage;
    }

    /**
     * @see Worker::$onWorkerStart
     * @param Worker $worker
     * @return void
     */
    public function onWorkerStart(Worker $worker): void
    {
        // 初始化储存
        $this->_storage = Redis::connection(Config::get('plugin.workbunny.webman-push-server.app.redis_channel', 'default'))->client();
        // 心跳检查
        $this->_heartbeatTimer = Timer::add($this->_keepaliveTimeout / 2, function (){
            foreach ($this->_connections as $connection) {
                if (($count = $this->_getConnectionProperty($connection, 'clientNotSendPingCount')) > 1) {
                    $connection->destroy();
                }
                $this->_setConnectionProperty($connection, 'clientNotSendPingCount', $count + 1);
            }
        });
    }

    /**
     * @see Worker::$onWorkerStop
     * @param Worker $worker
     * @return void
     */
    public function onWorkerStop(Worker $worker): void{
        if($this->_storage instanceof \Redis){
            try {
                $this->_storage->close();
                $this->_storage = null;
            }catch (RedisException $exception){}
        }
        if($this->_heartbeatTimer !== null){
            Timer::del($this->_heartbeatTimer);
            $this->_heartbeatTimer = null;
        }
    }

    /**
     * @see Worker::$onConnect
     * @param TcpConnection $connection
     * @return void
     */
    public function onConnect(TcpConnection $connection): void
    {
        // 设置websocket握手事件回调
        $this->_setConnectionProperty($connection, 'onWebSocketConnect', function(TcpConnection $connection, string $header) {
            // 客户端有多少次没在规定时间发送心跳
            $this->_setConnectionProperty($connection, 'clientNotSendPingCount', 0);

            // /app/1234567890abcdefghig?protocol=7&client=js&version=3.2.4&flash=false
            if (!preg_match('/ \/app\/([^\/^\?^ ]+)/', $header, $match)) {
                $this->error($connection, null, 'Invalid app');
                $connection->pauseRecv();
                return;
            }

            if(!$this->getConfig('app_query')($appKey = $match[1])){
                $this->error($connection, null, "Invalid app_key");
                $connection->pauseRecv();
                return;
            }

            $this->_setConnectionProperty($connection, 'clientNotSendPingCount', 0);
            $this->_setConnectionProperty($connection, 'appKey', $appKey);
            $this->_setConnectionProperty($connection, 'socketId', $socketId = $this->_createSocketId());
            $this->_setConnectionProperty($connection, 'channels', ['' => '']);
            $this->_setConnection($connection, $appKey, '');

            /**
             * 向客户端发送链接成功的消息
             * {"event":"pusher:connection_established","data":"{\"socket_id\":\"208836.27464492\",\"activity_timeout\":120}"}
             */
            $this->send($connection, null, null, [
                'event' => EVENT_CONNECTION_ESTABLISHED,
                'data'  => json_encode([
                    'socket_id'        => $socketId,
                    'activity_timeout' => 55
                ])
            ]);
        });
    }

    /**
     * @see Worker::$onClose
     * @param TcpConnection $connection
     * @return void
     */
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

    /**
     * @see Worker::$onMessage
     * @param TcpConnection $connection
     * @param mixed $data
     * @return void
     */
    public function onMessage(TcpConnection $connection, $data): void
    {
        if(is_string($data)){
            $this->_setConnectionProperty($connection, 'clientNotSendPingCount', 0);
            if(!$data = json_decode($data, true)){
                return;
            }

            if($factory = AbstractEvent::factory($data['event'])){
                $factory->response($this, $connection, $data);
                return;
            }
            $this->error($connection,null, 'Client event rejected - Unknown event');
        }
    }

    /**
     * 发布事件
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
            // {"event":"my-event","data":"{\"message\":\"hello world\"}","channel":"my-channel"}
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
        $connection->send(json_encode([
            'event' => EVENT_ERROR,
            'data'  => array_merge([
                'code'    => $code,
                'message' => $message
            ], $extra)
        ], JSON_UNESCAPED_UNICODE));
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
        if(AbstractEvent::pre($event) === AbstractEvent::SERVER_EVENT) {
            try {
                HookServer::publish( AbstractEvent::SERVER_EVENT, array_merge($response, [
                    'id' => uuid(),
                ]));
            }catch (RedisException $exception){
                error_log($exception->getMessage() . PHP_EOL);
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
     * @return void
     */
    public function _unsetConnection(TcpConnection $connection, string $appKey, string $channel): void
    {
        unset($this->_connections[$appKey][$channel][$this->_getConnectionProperty($connection, 'socketId')]);
    }

    /**
     * @param TcpConnection $connection
     * @param string $property = pushServerConnectionExtra | clientNotSendPingCount
     * @param mixed|null $value
     * @return void
     */
    public function _setConnectionProperty(TcpConnection $connection, string $property, $value): void
    {
        $connection->$property = $value;
    }

    /**
     * @param TcpConnection $connection
     * @param string $property = pushServerConnectionExtra | clientNotSendPingCount
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
            false !== ($keys = $this->getStorage()->scan($iterator, $this->_getUserStorageKey($appKey, $channel),100))
        ) {
            foreach($keys as $key) {
                $result = $this->getStorage()->hGetAll($key);
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
        return $channel !== null ?
            "workbunny:webman-push-server:appKey_$appKey:channel_*:info" :
            "workbunny:webman-push-server:appKey_$appKey:channel_$channel:info";
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
     * @param string $channel
     * @param string|null $uid
     * @return string
     */
    public function _getUserStorageKey(string $appKey, string $channel, ?string $uid = null): string
    {
        return $uid !== null ?
            "workbunny:webman-push-server:appKey_$appKey:channel_$channel:uid_*" :
            "workbunny:webman-push-server:appKey_$appKey:channel_$channel:uid_$uid";
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
}

