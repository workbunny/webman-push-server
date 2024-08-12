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
use stdClass;
use support\Log;
use Workbunny\WebmanPushServer\PublishTypes\AbstractPublishType;
use Workbunny\WebmanPushServer\PushServer;
use Workerman\Connection\TcpConnection;
use function Workbunny\WebmanPushServer\ms_timestamp;
use function Workbunny\WebmanPushServer\uuid;
use const Workbunny\WebmanPushServer\CHANNEL_TYPE_PRESENCE;
use const Workbunny\WebmanPushServer\CHANNEL_TYPE_PRIVATE;
use const Workbunny\WebmanPushServer\CHANNEL_TYPE_PUBLIC;
use const Workbunny\WebmanPushServer\EVENT_CHANNEL_VACATED;
use const Workbunny\WebmanPushServer\EVENT_MEMBER_REMOVED;
use const Workbunny\WebmanPushServer\EVENT_UNSUBSCRIPTION_SUCCEEDED;

class Unsubscribe extends AbstractEvent
{
    /**
     * @inheritDoc
     */
    public function response(TcpConnection $connection, array $request): void
    {
        $channel = $request['data']['channel'] ?? '';
        switch ($channelType = PushServer::getChannelType($channel)) {
            case CHANNEL_TYPE_PUBLIC:
            case CHANNEL_TYPE_PRIVATE:
                self::unsubscribeChannel($connection, $channel, $channelType);
                return;
            case CHANNEL_TYPE_PRESENCE:
                $userData = json_decode($request['data']['channel_data'] ?? '{}', true);
                if (!$userData or !isset($userData['user_id'])) {
                    PushServer::error($connection, '400', 'Client error - Bad channel_data');
                    return;
                }
                self::unsubscribeChannel($connection, $channel, $userData['user_id']);
                break;
            default:
                PushServer::error($connection, '403', 'Client rejected - Bad channel_type');
        }
    }

    /**
     * 客户端取消订阅channel
     *
     * @param TcpConnection $connection 客户端连接
     * @param string $channel 取消订阅的通道
     * @param string|null $uid 用户id
     * @return void
     */
    public static function unsubscribeChannel(TcpConnection $connection, string $channel, ?string $uid = null): void
    {
        try {
            $appKey = PushServer::getConnectionProperty($connection, 'appKey');
            $socketId = PushServer::getConnectionProperty($connection, 'socketId');
            $channels = PushServer::getConnectionProperty($connection, 'channels');

            if ($type = $channels[$channel] ?? null) {
                $storage = PushServer::getStorageClient();
                // presence通道
                if ($type === CHANNEL_TYPE_PRESENCE) {
                    if ($users = $storage->keys(PushServer::getUserStorageKey($appKey, $channel, $uid))) {
                        $userCount = $storage->hIncrBy(PushServer::getChannelStorageKey($appKey, $channel), 'user_count', -count($users));
                        if ($userCount <= 0) {
                            $storage->del(...$users);
                        }
                        /**
                         * 向通道广播成员移除事件
                         *
                         * {"event":"pusher_internal:member_removed","data":"{"user_id":"14884657801"}","channel":"presence-channel"}
                         */
                        PushServer::publishUseRetry(AbstractPublishType::PUBLISH_TYPE_CLIENT, [
                            'appKey'    => $appKey,
                            'socketId'  => $socketId,
                            'timestamp' => ms_timestamp(),
                            'channel'   => $channel,
                            'event'     => EVENT_MEMBER_REMOVED,
                            'data'      => [
                                'id'      => uuid(),
                                'user_id' => $uid
                            ],
                        ]);
                    }
                }
                // 查询通道订阅数量
                $subCount = $storage->hIncrBy($key = PushServer::getChannelStorageKey($appKey, $channel), 'subscription_count', -1);
                if ($subCount <= 0) {
                    $storage->del($key);
                    // 内部事件广播 通道被移除事件
                    PushServer::publishUseRetry(AbstractPublishType::PUBLISH_TYPE_SERVER, [
                        'appKey'    => $appKey,
                        'socketId'  => $socketId,
                        'timestamp' => ms_timestamp(),
                        'channel'   => $channel,
                        'event'     => EVENT_CHANNEL_VACATED,
                        'data'      => [
                            'id'      => uuid(),
                            'app_key' => $appKey,
                            'channel' => $channel,
                            'time_ms' => microtime(true)
                        ]
                    ]);
                }
                // 移除通道
                unset($channels[$channel]);
                PushServer::setConnectionProperty($connection, 'channels', $channels);
                PushServer::unsetChannels($appKey, $channel, $socketId);
                /**
                 * 发送退订成功事件消息
                 *
                 * @private-channel:{"event":"pusher_internal:unsubscription_succeeded","data":{},"channel":"my-channel"}
                 * @public-channel:{"event":"pusher_internal:unsubscription_succeeded","data":{},"channel":"my-channel"}
                 * @presence-channel:{"event":"pusher_internal:unsubscription_succeeded","data":{},"channel":"my-channel"}
                 **/
                PushServer::send($connection, $channel, EVENT_UNSUBSCRIPTION_SUCCEEDED, new stdClass());
            }
        } catch (RedisException $exception) {
            PushServer::error($connection, '500', 'Server error - Unsubscribe');
            Log::channel('plugin.workbunny.webman-push-server.error')
                ->error("[PUSH-SERVER] {$exception->getMessage()}", [
                    'method' => __METHOD__
                ]);
        }
    }
}