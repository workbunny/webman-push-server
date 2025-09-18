<?php declare(strict_types=1);
/**
 * @author workbunny/Chaz6chez
 * @email chaz6chez1993@outlook.com
 */

namespace Workbunny\WebmanPushServer\Traits;

use InvalidArgumentException;
use RedisException;
use support\Log;
use support\Redis;
use Workerman\Redis\Client;
use Workerman\Timer;

/**
 * @method static _subscribeRaw($channel, $raw) 订阅原始消息
 */
trait ChannelMethods
{
    /** @var Client[] */
    protected static array $_redisClients = [];

    /** @var string  */
    protected static string $internalChannelKey = 'workbunny.webman-push-server.server-channel';


    /**
     * 创建连接
     *
     * @param string $redisChannel redis通道
     * @return Client
     */
    public static function channelConnect(string $redisChannel = 'server-channel'): Client
    {
        if (!(static::$_redisClients[$redisChannel] ?? null)) {
            if (!$config = config('redis')["plugin.workbunny.webman-push-server.$redisChannel"] ?? []) {
                throw new InvalidArgumentException("Redis channel [$redisChannel] not found. ");
            }
            $client = new Client(sprintf('redis://%s:%s', $config['host'], $config['port']), $config['options'] ?? []);
            $client->connect();
            if ($passport = $config['password'] ?? null) {
                $client->auth($passport);
            }
            if ($database = $config['database'] ?? 0) {
                $client->select($database);
            }
            static::$_redisClients[$redisChannel] = $client;
        }
        return static::$_redisClients[$redisChannel];
    }

    /**
     * 关闭连接
     *
     * @param string|null $redisChannel redis通道
     * @return void
     */
    public static function channelClose(?string $redisChannel = 'server-channel'): void
    {
        if ($redisChannel === null) {
            foreach (static::$_redisClients as $client) {
                $client->close();
            }
            static::$_redisClients = [];
            return;
        }
        if (static::$_redisClients[$redisChannel] ?? null) {
            static::$_redisClients[$redisChannel]->close();
            unset(static::$_redisClients[$redisChannel]);
        }
    }

    /**
     * 订阅
     *
     * @param string $redisChannel redis通道
     * @return void
     */
    public static function subscribe(string $redisChannel = 'server-channel'): void
    {
        static::channelConnect($redisChannel)
            ->subscribe(static::$internalChannelKey, [static::class, '_onSubscribe']);
    }

    /**
     * 发送消息
     *
     * @param string $type 消息类型
     * @param array $data 消息数据
     * @param string $redisChannel redis通道
     * @return bool|int|\Redis
     * @throws RedisException
     */
    public static function publish(string $type, array $data, string $redisChannel = 'server-channel'): bool|int|\Redis
    {
        if (!(config('redis')[$redisChannel = "plugin.workbunny.webman-push-server.$redisChannel"] ?? [])) {
            throw new InvalidArgumentException("Redis channel [$redisChannel] not found. ");
        }
        return Redis::connection($redisChannel)->client()->publish(
            static::$internalChannelKey,
            json_encode([
                'type' => $type,
                'data' => $data
            ], JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * 尝试重试的发布消息
     *
     * @param string $type 消息类型
     * @param array $data 消息数据
     * @param float $retryInterval 重试间隔
     * @param string $redisChannel redis通道
     * @return int|bool|null
     */
    public static function publishUseRetry(string $type, array $data, float $retryInterval = 0.5, string $redisChannel = 'server-channel'): null|int|bool
    {
        try {
            $res = static::publish($type, $data, $redisChannel);
        } catch (RedisException $exception) {
            Log::channel('plugin.workbunny.webman-push-server.error')
                ->error("[CHANNEL-PUBLISH-RETRY] {$exception->getMessage()}", [
                    'args'   => func_get_args(),
                    'method' => __METHOD__,
                ]);
            $res = false;
        }
        if ($res === false) {
            return $timerId = Timer::add($retryInterval, function () use (
                &$timerId, $redisChannel, $type, $data
            ) {
                if (static::publish($type, $data, $redisChannel) !== false) {
                    Timer::del($timerId);
                }
            });
        }
        return $res;
    }

    /**
     * 订阅回调
     *
     * @param $channel
     * @param $raw
     * @return void
     */
    public static function _onSubscribe($channel, $raw): void
    {
        if (is_callable([static::class, '_subscribeRaw'])) {
            static::_subscribeRaw($channel, $raw);
        }
        $message = @json_decode($raw, true);
        if (
            ($type = $message['type'] ?? null) and
            ($data = $message['data'] ?? null)
        ) {
            // 订阅响应
            static::_subscribeResponse($type, $data, $raw);
        } else {
            Log::channel('plugin.workbunny.webman-push-server.notice')->notice(
                "[Channel] $channel -> $message format error. "
            );
        }
    }

    /**
     * 订阅响应
     *
     * @param string $type 消息类型
     * @param array $data 解析后的消息数据
     * @return void
     */
    abstract public static function _subscribeResponse(string $type, array $data): void;
}