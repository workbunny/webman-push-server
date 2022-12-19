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
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Timer;

class Client
{
    /**
     * @var Client[]
     */
    protected static array $_client = [];

    /** @var AsyncTcpConnection|null ws连接 */
    protected ?AsyncTcpConnection $_connection = null;
    /** @var string 地址 */
    protected string $_address;
    /**
     * @var array = [
     *      'context_option' => [],
     *      'heartbeat'      => 60
     * ]
     */
    protected array $_config = [];
    /** @var string|null 客户端id */
    protected ?string $_socketId = null;
    /** @var Closure[]  */
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
     * @param string $address
     * @param array $config
     * @return Client
     */
    public static function connection(string $address, array $config = []): Client
    {
        if(!self::$_client[$address] ?? null){
            self::$_client[$address] = new Client($address, $config);
        }
        return self::$_client[$address];
    }

    /**
     * @return void
     */
    public function connect(): void
    {
        if(!$this->_connection){
            try {
                $this->_connection = new AsyncTcpConnection("ws://{$this->_address}", $this->_config['context_option'] ?? []);
            }catch (\Throwable $throwable){
                throw new \RuntimeException($throwable->getMessage(), $throwable->getCode(), $throwable);
            }
            $this->_connection->onConnect = function (){
                if(!$this->_heartbeatTimer){
                    $this->_heartbeatTimer = Timer::add($this->_config['heartbeat'] ?? 60, function (){
                        $this->_connection->send('{"event":"pusher:ping","data":{}}');
                    });
                }
            };
            $this->_connection->onMessage = function (AsyncTcpConnection $connection, $buffer){
                if(is_string($buffer)){
                    $this->_handler($connection, $buffer);
                }
            };
            $this->_connection->onClose = function (){
                $this->disconnect();
            };
            $this->_connection->connect();
        }
    }

    /**
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
     * @param string $event
     * @param Closure $closure = function(AsyncTcpConnection $connection, string $buffer){}
     * @return void
     */
    public function on(string $event, Closure $closure): void
    {
        $this->_events[$event] = $closure;
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
     * @return array
     */
    public function getConfig(): array
    {
        return $this->_config;
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
     * @param AsyncTcpConnection $connection
     * @param string $buffer
     * @return void
     */
    public function _handler(AsyncTcpConnection $connection, string $buffer): void
    {
        if($data = json_decode($buffer, true)){
            $channel = $data['channel'] ?? null;
            $event = $data['event'] ?? null;
            $data = $data['data'] ?? [];
            if ($event === EVENT_PONG) {
                return;
            }
            if ($event === EVENT_SUBSCRIPTION_SUCCEEDED) {
                $this->_channels[$channel] = $channel;
            }
            if ($event === EVENT_UNSUBSCRIPTION_SUCCEEDED) {
                unset($this->_channels[$channel]);
            }
            if ($event === EVENT_CONNECTION_ESTABLISHED) {
                $this->_socketId = $data['socket_id'] ?? null;
            }
            if(isset($this->_events[$event])){
                call_user_func($this->_events[$event], $connection, $buffer);
            }
        }
    }
}