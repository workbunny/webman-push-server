<?php declare(strict_types=1);
/**
 * @author workbunny/Chaz6chez
 * @email chaz6chez1993@outlook.com
 */

namespace Workbunny\WebmanPushServer\Traits;

use RedisException;
use support\Redis;
use Workerman\Redis\Client;
use Workerman\Timer;

trait ChannelMethods
{
    /** @var Client[] */
    protected static array $_redisClients = [];

    /** @var string  */
    protected static string $internalChannelKey = 'workbunny.webman-push-server.server-channel';

    public static string $publishTypeClient = 'client';

    public static string $publishTypeServer = 'server';

    /**
     * 通配符校验
     *
     * @param string $rule 带有通配符的规则字符串
     * @param string $input 待校验字符串
     * @return bool
     */
    public static function wildcard(string $rule, string $input): bool
    {
        $regex = '/^' . str_replace('?', '.',
                str_replace('*', '.+', $rule)
            ) . '$/';
        preg_match($regex, $input, $match);
        return !empty($match);
    }

    /**
     * 创建连接
     *
     * @param string $redisChannel redis通道
     * @return Client
     */
    public static function connect(string $redisChannel = 'server-channel'): Client
    {
        if (!(self::$_redisClients[$redisChannel] ?? null)) {
            if (!$config = config('redis')["plugin.workbunny.webman-push-server.$redisChannel"] ?? []) {
                throw new \InvalidArgumentException("Redis channel [$redisChannel] not found. ");
            }
            $client = new Client(sprintf('redis://%s:%s', $config['host'], $config['port']), $config['options'] ?? []);
            $client->connect();
            if ($passport = $config['password'] ?? null) {
                $client->auth($passport);
            }
            if ($database = $config['database'] ?? 0) {
                $client->select($database);
            }
            self::$_redisClients[$redisChannel] = $client;
        }
        return self::$_redisClients[$redisChannel];
    }

    /**
     * 关闭连接
     *
     * @param string|null $redisChannel redis通道
     * @return void
     */
    public static function close(?string $redisChannel = 'server-channel'): void
    {
        if ($redisChannel === null) {
            foreach (self::$_redisClients as $client) {
                $client->close();
            }
            self::$_redisClients = [];
            return;
        }
        if (self::$_redisClients[$redisChannel] ?? null) {
            self::$_redisClients[$redisChannel]->close();
            unset(self::$_redisClients[$redisChannel]);
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
        self::connect($redisChannel)
            ->subscribe(static::$internalChannelKey, function ($channel, $message) {
                $message = @json_decode($message, true);
                if (
                    ($type = $message['type'] ?? null) and
                    ($data = $message['data'] ?? null)
                ) {
                    // 订阅响应
                    static::_subscribeResponse($type, $data);
                }
            });
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
        if (!config('redis')["plugin.workbunny.webman-push-server.$redisChannel"] ?? []) {
            throw new \InvalidArgumentException("Redis channel [$redisChannel] not found. ");
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
     * @return int|bool
     */
    public static function publishUseRetry(string $type, array $data, float $retryInterval = 0.5, string $redisChannel = 'server-channel'): int|bool
    {
        try {
            if (($res = self::publish($type, $data, $redisChannel)) === false) {
                return $timerId = Timer::add($retryInterval, function () use (
                    &$timerId, $redisChannel, $type, $data
                ) {
                    if (self::publish($type, $data, $redisChannel) !== false) {
                        Timer::del($timerId);
                    }
                });
            }
        } catch (\Throwable) {
            $res = false;
        }
        return $res;
    }

    /**
     * 订阅响应
     *
     * @param string $type 消息类型
     * @param array $data 消息数据
     * @return void
     */
    abstract protected static function _subscribeResponse(string $type, array $data): void;
}