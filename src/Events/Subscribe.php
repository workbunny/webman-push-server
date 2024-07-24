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

namespace Workbunny\WebmanPushServer\Events;

use RedisException;
use support\Log;
use Workbunny\WebmanPushServer\PublishTypes\AbstractPublishType;
use Workbunny\WebmanPushServer\PushServer;
use Workerman\Connection\TcpConnection;
use function Workbunny\WebmanPushServer\uuid;
use const Workbunny\WebmanPushServer\CHANNEL_TYPE_PRESENCE;
use const Workbunny\WebmanPushServer\CHANNEL_TYPE_PRIVATE;
use const Workbunny\WebmanPushServer\CHANNEL_TYPE_PUBLIC;
use const Workbunny\WebmanPushServer\EVENT_CHANNEL_OCCUPIED;
use const Workbunny\WebmanPushServer\EVENT_MEMBER_ADDED;
use const Workbunny\WebmanPushServer\EVENT_SUBSCRIPTION_SUCCEEDED;

class Subscribe extends AbstractEvent
{
    /**
     * @param PushServer $pushServer
     * @param TcpConnection $connection
     * @param array $request = [
     *      'event' => 'pusher:subscribe',
     *      'data'  => [
     *          'channel'      => 'presence-channel',
     *          'auth'         => 'b054014693241bcd9c26:10e3b628cb78e8bc4d1f44d47c9294551b446ae6ec10ef113d3d7e84e99763e6',
     *          'channel_data' => [
     *              'user_id'   => 100,
     *              'user_info' => "{\'name\':\'123\'}"
     *          ]
     *      ]
     * ]
     * @return void
     * @inheritDoc
     */
    public function response(TcpConnection $connection, array $request): void
    {
        $channel = $request['data']['channel'] ?? '';
        $channelData = $request['data']['channel_data'] ?? [];
        $clientAuth = $request['data']['auth'] ?? '';
        $appsCallback = PushServer::getConfig('apps_query', getBase: true);

        // private- 和 presence- 开头的channel需要验证
        switch ($channelType = PushServer::getChannelType($channel)){
            case CHANNEL_TYPE_PRESENCE:
                if (!$channelData) {
                    PushServer::error($connection, '400', 'Client error - Empty channel_data');
                    return;
                }
                if ($appsCallback) {
                    $auth = self::auth(
                        $appKey = PushServer::getConnectionProperty($connection, 'appKey'),
                        call_user_func($appsCallback, $appKey)['app_secret'] ?? '',
                        PushServer::getConnectionProperty($connection, 'socketId'),
                        $channel,
                        $channelData
                    );
                } else {
                    $auth = '';
                    Log::channel('plugin.workbunny.webman-push-server.warning')
                        ->warning("[PUSH-SERVER] Subscribe auth error, Config apps_query not found. ", [
                            'request' => $request,
                            'method'  => __METHOD__
                        ]);
                }
                if ($clientAuth !== $auth) {
                    PushServer::error($connection, '403', 'Client rejected - Received invalid Auth ' . $clientAuth);
                    return;
                }
                if (!isset($channelData['user_id']) or !is_string($channelData['user_id'])) {
                    PushServer::error($connection,'400', 'Client error - Bad channel_data.user_id');
                    return;
                }
                if (!isset($channelData['user_info']) or !is_string($channelData['user_info'])) {
                    PushServer::error($connection,'400', 'Client error - Bad channel_data.user_info');
                    return;
                }
                self::subscribeChannel($connection, $channel, $channelType, $channelData['user_id'], $channelData['user_info']);
                break;
            case CHANNEL_TYPE_PRIVATE:
                if ($appsCallback) {
                    $auth = self::auth(
                        $appKey = PushServer::getConnectionProperty($connection, 'appKey'),
                        call_user_func($appsCallback, $appKey)['app_secret'] ?? '',
                        PushServer::getConnectionProperty($connection, 'socketId'),
                        $channel,
                        $channelData
                    );
                } else {
                    $auth = '';
                    Log::channel('plugin.workbunny.webman-push-server.warning')
                        ->warning("[PUSH-SERVER] Subscribe auth error, Config apps_query not found. ", [
                            'request' => $request,
                            'method'  => __METHOD__
                        ]);
                }
                if ($clientAuth !== $auth) {
                    PushServer::error($connection,'403', 'Client rejected - Received invalid Auth ' . $clientAuth);
                    return;
                }
                self::subscribeChannel($connection, $channel, $channelType);
                break;
            case CHANNEL_TYPE_PUBLIC:
                self::subscribeChannel($connection, $channel, $channelType);
                break;
            default:
                PushServer::error($connection, '403', 'Client rejected - Bad channel_type');
                break;
        }
    }

    /**
     * @param string $appKey
     * @param string $appSecret
     * @param string $socketId
     * @param string $channel
     * @param array $channelData
     * @return string
     */
    public static function auth(string $appKey, string $appSecret, string $socketId, string $channel, array $channelData = []): string
    {
        if ($channelData) {
            ksort($channelData);
            return $appKey . ':' . hash_hmac('sha256', $socketId . ':' . $channel . ':' . json_encode($channelData, JSON_UNESCAPED_UNICODE), $appSecret);
        }
        return $appKey . ':' . hash_hmac('sha256', $socketId . ':' . $channel, $appSecret);
    }

    /**
     * 客户端订阅channel
     *
     * @param TcpConnection $connection
     * @param string $channel
     * @param string $type = public | private | presence
     * @param string ...$params [$userId, $userInfo]
     * @return void
     */
    public static function subscribeChannel(TcpConnection $connection, string $channel, string $type, string ...$params): void
    {
        try {
            $appKey = PushServer::getConnectionProperty($connection, 'appKey');
            $socketId = PushServer::getConnectionProperty($connection, 'socketId');
            $channels = PushServer::getConnectionProperty($connection, 'channels');
            $userId = $params[0] ?? 'unknown';
            $userInfo = $params[1] ?? '{}';
            // 为当前进程增加订阅的通道
            PushServer::setChannel($appKey, $channel, $socketId);

            $storage = PushServer::getStorageClient();
            // 通道是否已经被建立
            $channelExists = $storage->exists($key = PushServer::getChannelStorageKey($appKey, $channel));
            if (!$channelExists) {
                /** @see PushServer::$_storage */
                $storage->hSet($key, 'type', $type);
                // 内部事件广播 通道被创建事件
                PushServer::publishUseRetry(AbstractPublishType::PUBLISH_TYPE_SERVER, [
                    'appKey'    => $appKey,
                    'channel'   => $channel,
                    'event'     => EVENT_CHANNEL_OCCUPIED,
                    'data'      => [
                        'id'      => uuid(),
                        'app_key' => $appKey,
                        'channel' => $channel,
                        'time_ms' => microtime(true)
                    ]
                ]);
            }
            // 当前连接是否订阅过该channel
            if (!isset($channels[$channel])) {
                $channels[$channel] = $type;
                PushServer::setConnectionProperty($connection, 'channels', $channels);
                PushServer::setConnection($appKey, $socketId, $connection);
                // 递增订阅数
                /** @see PushServer::$_storage */
                $storage->hIncrBy($key,'subscription_count', 1);
            }
            // 如果是presence通道
            if ($isPresence = ($type === CHANNEL_TYPE_PRESENCE)) {
                if (!$storage->exists($userKey = PushServer::getUserStorageKey($appKey, $channel, $userId))) {
                    $storage->hIncrBy($key ,'user_count', 1);
                    $storage->hMSet($userKey, [
                        'user_id'   => $userId,
                        'user_info' => $userInfo,
                        'socket_id' => $socketId
                    ]);

                    /**
                     * 向通道广播成员添加事件
                     *
                     * {"event":"pusher_internal:member_added","data":{"user_id":1488465780,"user_info":"{\"name\":\"123\",\"sex:\"1\"}","channel ":"presence-channel"}}
                     */
                    PushServer::publishUseRetry(AbstractPublishType::PUBLISH_TYPE_CLIENT, [
                        'appKey'   => $appKey,
                        'channel'  => $channel,
                        'event'    => EVENT_MEMBER_ADDED,
                        'data'     => [
                            'id'        => uuid(),
                            'user_id'   => $userId,
                            'user_info' => $userInfo
                        ],
                        'socketId' => $socketId
                    ]);
                }
            }
            /**
             * 发送订阅成功消息
             *
             * @private-channel:{"event":"pusher_internal:subscription_succeeded","data":{},"channel":"my-channel"}
             * @public-channel:{"event":"pusher_internal:subscription_succeeded","data":{},"channel":"my-channel"}
             * @presence-channel:{"event":"pusher_internal:subscription_succeeded","data":{"presence":{"count":2,"ids":["1488465780","14884657802"],"hash":{"1488465780":{"name":"123","sex":"1"},"14884657802":{"name":"123","sex":"1"}}}},"channel":"presence-channel"}
             */
            PushServer::send(
                $connection,
                $channel,
                EVENT_SUBSCRIPTION_SUCCEEDED,
                $isPresence ?
                    PushServer::getPresenceChannelDataForSubscribe($appKey, $channel) :
                    new \stdClass()
            );
        } catch (RedisException $exception) {
            PushServer::error($connection, '500', 'Server error - Subscribe');
            Log::channel('plugin.workbunny.webman-push-server.error')
                ->error("[PUSH-SERVER] {$exception->getMessage()}", [
                    'method' => __METHOD__
                ]);
        }
    }
}