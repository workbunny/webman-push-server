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
use RuntimeException;
use Throwable;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Http\Client as HttpClient;
use Workerman\Http\Response;
use Workerman\Timer;

class Client
{
    public static array $header = [
        'Client'  => 'workbunny-client',
        'Version' => VERSION
    ];
    /** @var Client[] */
    protected static array $_client = [];
    /** @var HttpClient|null  */
    protected static ?HttpClient $_httpClient = null;
    /** @var int  */
    protected static int $_connectTimeout = 30;
    /** @var int  */
    protected static int $_requestTimeout = 30;
    /** @var AsyncTcpConnection|null ws连接 */
    protected ?AsyncTcpConnection $_connection = null;
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
    /** @var array 监听的通道 */
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
     * @return HttpClient
     */
    public static function getHttpClient(): HttpClient
    {
        if(!self::$_httpClient instanceof HttpClient){
            self::$_httpClient = new HttpClient([
                'connect_timeout' => self::$_connectTimeout,
                'timeout'         => self::$_requestTimeout,
            ]);
        }
        return self::$_httpClient;
    }

    /**
     * @param string $address
     * @param array $config = [
     *    @see Client::$_config
     * ]
     * @return Client
     */
    public static function connection(string $address, array $config = []): Client
    {
        if(!isset(self::$_client[$address])){
            self::$_client[$address] = new Client($address, $config);
        }
        return self::$_client[$address];
    }

    /**
     * 创建连接
     * @return void
     */
    public function connect(): void
    {
        $queryString = http_build_query(array_merge(self::$header, $this->getConfig('query', [])));
        if(!$this->_connection){
            try {
                $this->_connection = new AsyncTcpConnection(
                    "ws://{$this->getAddress()}/app/{$this->getConfig('app_key')}?$queryString",
                    $this->getConfig('context_option', [])
                );
            }catch (Throwable $throwable){
                throw new RuntimeException($throwable->getMessage(), $throwable->getCode(), $throwable);
            }
            $this->_connection->onConnect = function (){
                if(!$this->_heartbeatTimer){
                    $this->_heartbeatTimer = Timer::add($this->getConfig('heartbeat', 60), function (){
                        $this->_connection->send('{"event":"pusher:ping","data":{}}');
                    });
                }
            };
            $this->_connection->onMessage = function (AsyncTcpConnection $connection, $buffer){
                if(is_string($buffer)){
                    $this->_onMessageHandler($connection, $buffer);
                }
            };
            $this->_connection->onClose = function (){
                $this->disconnect();
            };
            $this->_connection->connect();
        }
    }

    /**
     * 注册事件回调函数
     * @param string|null $channel
     * @param string $event
     * @param Closure $handler = function(AsyncTcpConnection $connection, array $data){}
     * @return void
     */
    public function on(?string $channel, string $event, Closure $handler): void
    {
        if($channel !== null){
            $this->_events[$event][$channel] = $handler;
            return;
        }
        $this->_events[$event] = $handler;
    }

    /**
     * 向通道发送消息
     * @param string|null $channel
     * @param string $event
     * @param array $data
     * @return bool
     */
    public function trigger(string $channel, string $event, array $data = []): bool
    {
        if(strpos($event, 'client-') !== 0){
            throw new RuntimeException("Event $event should start with 'client-'");
        }

        if(isset($this->_channels[$channel])){
            $this->publish($channel, $event, $data);
            return true;
        }
        return false;
    }

    /**
     * 订阅通道
     * @param string $channel
     * @param Closure $handler = function(AsyncTcpConnection $connection, array $data){}
     * @return void
     */
    public function subscribe(string $channel, Closure $handler): void
    {
        // 注册事件回调
        $this->on($channel, EVENT_SUBSCRIPTION_SUCCEEDED, $handler);
        // public
        if(strpos($channel, 'private-') !== 0 and strpos($channel, 'presence-') !== 0) {
            $this->publish(null, EVENT_SUBSCRIBE, [
                'channel' => $channel,
            ]);
            return;
        }
        // private / presence
        // http 鉴权
        $this->_authRequest($channel, function(Response $response) use($channel){
            if($response->getStatusCode() === 200){
                if($res = json_decode((string)$response->getBody(),true)){
                    $res['channel'] = $channel;
                    $this->publish(null, EVENT_SUBSCRIBE, $res);
                }
            }
        }, function (Throwable $throwable) {});

    }

    /**
     * 取订通道
     * @param string $channel
     * @param Closure|null $handler = function(AsyncTcpConnection $connection, array $data){}
     * @return void
     */
    public function unsubscribe(string $channel, ?Closure $handler): void
    {
        $this->on($channel, EVENT_UNSUBSCRIPTION_SUCCEEDED, $handler);
        $this->publish(null, EVENT_UNSUBSCRIBE, [
            'channel' => $channel
        ]);
    }

    /**
     * 取订全部通道
     * @return void
     */
    public function unsubscribeAll(): void
    {
        $channels = $this->getChannels();
        foreach ($channels as $channel){
            $this->unsubscribe($channel, null);
        }
    }

    /**
     * @return string
     */
    public function getAddress(): string
    {
        return $this->_address;
    }

    /**
     * @return string|null
     */
    public function getSocketId(): ?string
    {
        return $this->_socketId;
    }

    /**
     * @param string|null $key
     * @param mixed $default
     * @return array|mixed|null
     */
    public function getConfig(?string $key = null, $default = null)
    {
        if($key === null){
            return $this->_config;
        }
        return $this->_config[$key] ?? $default;
    }

    /**
     * @param string|null $channel
     * @return array|false|mixed
     */
    public function getChannels(?string $channel = null)
    {
        return $channel === null ? $this->_channels : ($this->_channels[$channel] ?? false);
    }

    /**
     * @return Closure[]|Closure[][]
     */
    public function getEvents(): array
    {
        return $this->_events;
    }

    /**
     * 弹出事件回调
     * @param string|null $channel
     * @param string $event
     * @return Closure|Closure[]|null
     */
    public function emit(?string $channel, string $event)
    {
        if($channel !== null){
            return $this->_events[$event][$channel] ?? null;
        }
        return $this->_events[$event] ?? null;
    }

    /**
     * 消息发布
     * @param string|null $channel
     * @param string $event
     * @param array $data
     * @return void
     */
    public function publish(?string $channel, string $event, array $data = []): void
    {
        $data = [
            'event' => $event,
            'data'  => $data
        ];
        if($channel !== null){
            $data['channel'] = $channel;
        }
        $this->_connection->send(json_encode([
            'channel' => $channel,
            'event'   => $event,
            'data'    => $data
        ],JSON_UNESCAPED_UNICODE));
    }

    /**
     * 关闭连接
     * @return void
     */
    public function disconnect(): void
    {
        if($this->_connection){
            $this->_connection->close();
            $this->_connection = null;
        }
        if($this->_heartbeatTimer){
            Timer::del($this->_heartbeatTimer);
        }
        unset(self::$_client[$this->getAddress()]);
    }

    /**
     * 鉴权请求
     * @param string $channel
     * @param Closure $success = function(\Workerman\Http\Response $response){}
     * @param Closure $error = function(\Exception $exception){}
     * @return void
     */
    public function _authRequest(string $channel, Closure $success, Closure $error): void
    {
        $channelData = $this->getConfig('channel_data');
        $count = 0;
        $timerId = Timer::add(0.01, function () use ($channel, $channelData, $success, $error, &$count, &$timerId){
            if($count > (30 * 100)){ // 30s 订阅超时时间
                Timer::del($timerId);
                return;
            }
            if($this->getSocketId() === null){
                $count ++;
                return;
            }
            self::getHttpClient()->request(
                $this->getConfig('auth'),
                [
                    'method'    => 'POST',
                    'version'   => '1.1',
                    'headers'   => array_merge(self::$header,[
                        'Connection'   => 'keep-alive',
                        'Content-type' => 'application/json'
                    ]),
                    'data'      => json_encode([
                        'channel_name' => $channel,
                        'socket_id'    => $this->getSocketId(),
                        'channel_data' => $channelData ? json_encode($channelData, JSON_UNESCAPED_UNICODE) : null
                    ], JSON_UNESCAPED_UNICODE),
                    'success'   => $success,
                    'error'     => $error
                ]
            );
            Timer::del($timerId);
        });
    }

    /**
     * onMessage回调
     * @param AsyncTcpConnection $connection
     * @param string $buffer
     * @return void
     */
    public function _onMessageHandler(AsyncTcpConnection $connection, string $buffer): void
    {
        if($data = json_decode($buffer, true)){
            $channel = $data['channel'] ?? null;
            $event = $data['event'] ?? null;
            $data = $data['data'] ?? [];
            switch ($event) {
                // PONG
                case EVENT_PONG:
                    return;
                // 创建连接
                case EVENT_CONNECTION_ESTABLISHED:
                    $this->_socketId = $data['socket_id'];
                    break;
                // 关闭连接
                case EVENT_TERMINATE_CONNECTION:
                    $this->_socketId = null;
                    break;
                case EVENT_SUBSCRIPTION_SUCCEEDED:
                    $this->_channels[$channel] = $channel;
                    break;
                case EVENT_UNSUBSCRIPTION_SUCCEEDED:
                    unset($this->_channels[$channel]);
                    break;
                default:
                    break;
            }
            if($event) {
                $handler = $this->emit($channel, $event);
                if($handler instanceof Closure) {
                    call_user_func($handler, $connection, $data);
                }
            }
        }
    }
}