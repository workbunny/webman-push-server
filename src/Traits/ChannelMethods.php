<?php declare(strict_types=1);
/**
 * @author workbunny/Chaz6chez
 * @email chaz6chez1993@outlook.com
 */

namespace Workbunny\WebmanPushServer\Traits;

use RedisException;
use support\Log;
use Workbunny\WebmanPushServer\Channels\ChannelInterface;
use Workbunny\WebmanPushServer\Channels\RedisChannel;
use Workerman\Timer;

/**
 * @method static _subscribeRaw($channel, $raw) 订阅原始消息
 */
trait ChannelMethods
{
    /** @var ChannelInterface[] */
    protected static array $channels = [];

    /** @var string  */
    protected static string $internalChannelKey = 'workbunny.webman-push-server.server-channel';


    /**
     * 创建连接
     *
     * @param string $channelKey
     * @return ChannelInterface
     * @throws RedisException
     * @throws \Throwable
     */
    public static function channelConnect(string $channelKey = 'default'): ChannelInterface
    {
        if (!(static::$channels[$channelKey] ?? null)) {
            $handler = config("workbunny.webman-push-server.channel.$channelKey.handler");
            $channel = config("workbunny.webman-push-server.channel.$channelKey.channel");
            $handler = is_a($handler, ChannelInterface::class, true) ? new $handler($channel) : new RedisChannel(
                // 兼容旧版及其他可能的可选项
                $channelKey === 'default' ?
                    'server-channel' :
                    $channelKey
            );
            static::$channels[$channelKey] = $handler;
        }
        return static::$channels[$channelKey];
    }

    /**
     * 关闭连接
     *
     * @param string|null $channelKey
     * @return void
     */
    public static function channelClose(?string $channelKey = 'default'): void
    {
        if ($channelKey === null) {
            foreach (static::$channels as $client) {
                $client->unsubscribe();
            }
            static::$channels = [];
            return;
        }
        if (static::$channels[$channelKey] ?? null) {
            static::$channels[$channelKey]->unsubscribe();
            unset(static::$channels[$channelKey]);
        }
    }

    /**
     * 订阅
     *
     * @param string $channelKey
     * @return void
     * @throws RedisException
     * @throws \Throwable
     */
    public static function subscribe(string $channelKey = 'default'): void
    {
        static::channelConnect($channelKey)
            ->subscribe(static::$internalChannelKey, [static::class, '_onSubscribe']);
    }

    /**
     * 发送消息
     *
     * @param string $type 消息类型
     * @param array $data 消息数据
     * @param string $channelKey
     * @return bool|int
     * @throws RedisException
     * @throws \Throwable
     */
    public static function publish(string $type, array $data, string $channelKey = 'default'): bool|int
    {
        return static::channelConnect($channelKey)->publish(
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
     * @param string $channelKey
     * @return int|bool|null
     * @throws \Throwable
     */
    public static function publishUseRetry(string $type, array $data, float $retryInterval = 0.5, string $channelKey = 'default'): null|int|bool
    {
        try {
            $res = static::publish($type, $data, $channelKey);
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
                &$timerId, $channelKey, $type, $data
            ) {
                if (static::publish($type, $data, $channelKey) !== false) {
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