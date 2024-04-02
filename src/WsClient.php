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

use Closure;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Http\Response;
use Workerman\Timer;

class WsClient
{
    /** @var WsClient[] */
    protected static array $_wsClients= [];

    /** @var int  */
    protected static int $_connectTimeout = 30;

    /** @var int  */
    protected static int $_requestTimeout = 30;

    /** @var AsyncTcpConnection|null ws连接 */
    protected ?AsyncTcpConnection $_connection = null;

    /** @var Client|null  */
    protected ?Client $_client = null;

    /** @var string 地址 */
    protected string $_address;
    /**
     * @var array = [
     *      'app_key          => '',
     *      'heartbeat'       => 60,
     *      'auth'            => 'http://127.0.0.1:8002/auth',
     *      'query'           => [],
     *      'context_option'  => [],
     *      'channel_data'    => []
     * ]
     */
    protected array $_config = [];
    /** @var string|null 客户端id */
    protected ?string $_socketId = null;
    /** @var Closure[]|Closure[][]  */
    protected array $_events = [];
    /** @var string[]  */
    protected array $_channels = [];
    /** @var int|null 心跳定时 */
    protected ?int $_heartbeatTimer = null;

    /**
     * @param string $address
     * @param array $config
     */
    public function __construct(string $address, array $config = [])
    {
        $this->_address = $address;
        $this->_config = $config;
    }

    /**
     * 单例
     *
     * @param string $address
     * @param array $config = [
     *    @return WsClient
     *@see WsClient::$_config
     * ]
     */
    public static function instance(string $address, array $config = []): WsClient
    {
        if(!isset(self::$_wsClients[$address])){
            self::$_wsClients[$address] = new WsClient($address, $config);
        }
        return self::$_wsClients[$address];
    }

    /**
     * 获取单例
     *
     * @param string $address
     * @return WsClient|null
     */
    public static function getInstance(string $address): ?WsClient
    {
        return self::$_wsClients[$address] ?? null;
    }

    /**
     * 获取连接
     *
     * @return AsyncTcpConnection|null
     */
    public function getConnection() : ?AsyncTcpConnection
    {
        return $this->_connection;
    }

    /**
     * 设置连接
     *
     * @param AsyncTcpConnection|null $connection
     * @return void
     */
    public function setConnection(?AsyncTcpConnection $connection): void
    {
        $this->_connection = $connection;
    }

    /**
     * 获取http连接
     *
     * @return Client
     */
    public function getHttpClient(): Client
    {
        if(!$this->_client instanceof Client){
            $this->_client = new Client([
                'timeout' => $this->getConfig('http_timeout', 60),
                'proxy'   => $this->getConfig('proxy')
            ]);
        }
        return $this->_client;
    }

    /**
     * 设置心跳
     *
     * @param Closure|null $closure
     * @return void
     */
    public function setHeartbeat(?Closure $closure): void
    {
        $this->delHeartbeat();
        $this->_heartbeatTimer = Timer::add($this->getConfig('heartbeat', 60), $closure);
    }

    /**
     * 移除心跳
     *
     * @return void
     */
    public function delHeartbeat(): void
    {
        if ($this->_heartbeatTimer) {
            Timer::del($this->_heartbeatTimer);
        }
    }

    /**
     * 设置连接id
     *
     * @param string|null $socketId
     * @return void
     */
    public function setSocketId(?string $socketId): void
    {
        $this->_socketId = $socketId;
    }

    /**
     * 获取连接id
     *
     * @return string|null
     */
    public function getSocketId(): ?string
    {
        return $this->_socketId;
    }

    /**
     * 新增监听通道
     *
     * @param string $channel
     * @return void
     */
    public function addChannel(string $channel): void
    {
        $this->_channels[$channel] = $channel;
    }

    /**
     * 移除监听通道
     *
     * @param string $channel
     * @return void
     */
    public function delChannel(string $channel): void
    {
        unset($this->_channels[$channel]);
    }

    /**
     * 获取监听通道
     *
     * @return string[]
     */
    public function getChannels(): array
    {
        return $this->_channels;
    }

    /**
     * 获取地址
     *
     * @return string
     */
    public function getAddress(): string
    {
        return $this->_address;
    }

    /**
     * 获取配置
     *
     * @param string|null $key
     * @param mixed $default
     * @return array|mixed|null
     */
    public function getConfig(?string $key = null, mixed $default = null): mixed
    {
        if($key === null){
            return $this->_config;
        }
        return $this->_config[$key] ?? $default;
    }

    /**
     * 获取全部事件
     *
     * @return Closure[]|Closure[][]
     */
    public function getEvents(): array
    {
        return $this->_events;
    }

    /**
     * 事件注册
     *
     * @param string|null $channel
     * @param string $event
     * @param Closure $handler = function(AsyncTcpConnection $connection, array $data){}
     * @return void
     */
    public function eventOn(?string $channel, string $event, Closure $handler): void
    {
        if($channel !== null){
            $this->_events[$event][$channel] = $handler;
            return;
        }
        $this->_events[$event] = $handler;
    }

    /**
     * 事件取消
     *
     * @param string|null $channel
     * @param string $event
     * @return void
     */
    public function eventOff(?string $channel, string $event): void
    {
        if($channel !== null){
            unset($this->_events[$event][$channel]);
            return;
        }
        unset($this->_events[$event]);
    }

    /**
     * 事件回调
     *
     * @param string|null $channel
     * @param string $event
     * @return Closure|array|null
     */
    public function eventEmit(?string $channel, string $event): Closure|array|null
    {
        if($channel !== null){
            return $this->_events[$event][$channel] ?? null;
        }
        return $this->_events[$event] ?? null;
    }

    /**
     * 创建连接
     *
     * @return void
     */
    public function connect(): void
    {
        $queryString = http_build_query(array_merge([
            'X-Push-WS-Client' => 'push-server ' . VERSION
        ], $this->getConfig('query', [])));
        if(!$this->getConnection()){
            try {
                $this->setConnection(new AsyncTcpConnection(
                    "ws://{$this->getAddress()}/app/{$this->getConfig('app_key')}?$queryString",
                    $this->getConfig('context_option', [])
                ));
            } catch (Throwable $throwable) {
                throw new RuntimeException($throwable->getMessage(), $throwable->getCode(), $throwable);
            }
            $address = $this->getAddress();
            $this->getConnection()->onConnect = function () use ($address) {
                $client = self::getInstance($address);
                $client?->setHeartbeat(function () use ($client) {
                    $client?->getConnection()?->send('{"event":"pusher:ping","data":{}}');
                });
            };
            $this->getConnection()->onMessage = function (AsyncTcpConnection $connection, $buffer) use ($address) {
                if (is_string($buffer)) {
                    $client = self::getInstance($address);
                    $client?->_onMessageHandler($connection, $buffer);
                }
            };
            $this->getConnection()->onClose = function () use ($address) {
                $client = self::getInstance($address);
                $client?->disconnect();
            };
            $this->getConnection()->connect();
        }
    }

    /**
     * 关闭连接
     *
     * @return void
     */
    public function disconnect(): void
    {
        if($this->getConnection()){
            $this->getConnection()->close();
            $this->setConnection(null);
        }
        $this->delHeartbeat();
    }

    /**
     * 向通道发送消息
     *
     * @param array $channels
     * @param string $event
     * @param array $data
     * @return void
     */
    public function trigger(array $channels, string $event, array $data = []): void
    {
        if(!str_starts_with($event, 'client-')){
            throw new RuntimeException("Event $event should start with 'client-'");
        }
        foreach ($channels as $channel) {
            $this->publish($event, $data, $channel);
        }
    }

    /**
     * 消息发布
     *
     * @param string|null $channel
     * @param string $event
     * @param array $data
     * @return void
     */
    public function publish(string $event, array $data = [], ?string $channel = null): void
    {
        $data = [
            'event' => $event,
            'data'  => $data
        ];
        if($channel !== null){
            $data['channel'] = $channel;
        }
        $this->getConnection()->send(json_encode($data,JSON_UNESCAPED_UNICODE));
    }

    /**
     * 订阅通道
     *
     * @param string $channel
     * @param Closure $handler = function(AsyncTcpConnection $connection, array $data){}
     * @return void
     */
    public function subscribe(string $channel, Closure $handler): void
    {
        // 注册事件回调
        $this->eventOn($channel, EVENT_SUBSCRIPTION_SUCCEEDED, function () use ($handler, $channel) {
            // public
            if(!str_starts_with($channel, 'private-') and !str_starts_with($channel, 'presence-')) {
                $this->publish(EVENT_SUBSCRIBE, [
                    'channel' => $channel,
                ]);
                // 执行回调
                call_user_func($handler);
            }
            // private / presence
            // http 鉴权
            if ($this->_authRequest($channel)?->getStatusCode() === 200) {
                $this->publish(EVENT_SUBSCRIBE, [
                    'channel' => $channel,
                ]);
                // 执行回调
                call_user_func($handler);
            }
        });
    }

    /**
     * 取订通道
     *
     * @param string $channel
     * @return void
     */
    public function unsubscribe(string $channel): void
    {
        $this->publish(EVENT_UNSUBSCRIBE, [
            'channel' => $channel
        ]);
        $this->eventOff($channel, EVENT_UNSUBSCRIPTION_SUCCEEDED);
    }

    /**
     * 取订全部通道
     *
     * @return void
     */
    public function unsubscribeAll(): void
    {
        $channels = $this->getChannels();
        foreach ($channels as $channel){
            $this->unsubscribe($channel);
        }
    }

    /**
     * 鉴权请求
     *
     * @param string $channel
     * @return ResponseInterface|null
     */
    public function _authRequest(string $channel): ?ResponseInterface
    {
        $channelData = $this->getConfig('channel_data');
        $authUrl = $this->getConfig('auth');
        try {
            return $this->getHttpClient()->request('POST', $authUrl, [
                RequestOptions::HEADERS => [
                    'Connection' => 'keep-alive',
                    'Content-type' => 'application/json',
                    'X-Push-Ws-Client' => 'push-server ' . VERSION,
                    'X-Auth-Request' => true
                ],
                RequestOptions::JSON => [
                    'channel_name' => $channel,
                    'socket_id' => $this->getSocketId(),
                    'channel_data' => $channelData ? json_encode($channelData, JSON_UNESCAPED_UNICODE) : null
                ]
            ]);
        } catch (GuzzleException) {
            return null;
        }
    }

    /**
     * onMessage回调
     *
     * @param AsyncTcpConnection $connection
     * @param string $buffer
     * @return void
     */
    public function _onMessageHandler(AsyncTcpConnection $connection, string $buffer): void
    {
        if($buffer = json_decode($buffer, true)){
            $channel = $buffer['channel'] ?? null;
            $event = $buffer['event'] ?? null;
            $data = $buffer['data'] ?? [];
            switch ($event) {
                // PONG
                case EVENT_PONG:
                    return;
                // 创建连接
                case EVENT_CONNECTION_ESTABLISHED:
                    $this->setSocketId($data['socket_id']);
                    break;
                // 关闭连接
                case EVENT_TERMINATE_CONNECTION:
                    $this->setSocketId(null);
                    break;
                // 订阅成功
                case EVENT_SUBSCRIPTION_SUCCEEDED:
                    $this->addChannel($channel);
                    break;
                // 取消订阅
                case EVENT_UNSUBSCRIPTION_SUCCEEDED:
                    $this->delChannel($channel);
                    break;
                default:
                    break;
            }
            if($event) {
                $handler = $this->eventEmit($channel, $event);
                if ($handler instanceof Closure) {
                    call_user_func($handler, $connection, $buffer);
                }
            }
        }
    }
}