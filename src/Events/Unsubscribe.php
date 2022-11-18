<?php
declare(strict_types=1);

namespace Workbunny\WebmanPushServer\Events;

use RedisException;
use Workbunny\WebmanPushServer\HookServer;
use Workbunny\WebmanPushServer\Server;
use Workerman\Connection\TcpConnection;
use function Workbunny\WebmanPushServer\uuid;
use const Workbunny\WebmanPushServer\CHANNEL_TYPE_PRESENCE;
use const Workbunny\WebmanPushServer\CHANNEL_TYPE_PRIVATE;
use const Workbunny\WebmanPushServer\CHANNEL_TYPE_PUBLIC;
use const Workbunny\WebmanPushServer\EVENT_MEMBER_REMOVED;
use const Workbunny\WebmanPushServer\EVENT_UNSUBSCRIPTION_SUCCEEDED;
use const Workbunny\WebmanPushServer\PUSH_SERVER_EVENT_CHANNEL_VACATED;
use const Workbunny\WebmanPushServer\PUSH_SERVER_EVENT_MEMBER_REMOVED;

class Unsubscribe extends AbstractEvent
{
    /**
     * @inheritDoc
     */
    public function response(Server $pushServer, TcpConnection $connection, array $request): void
    {
        $channel = $request['data']['channel'];
        switch ($channelType = $pushServer->_getChannelType($channel)) {
            case CHANNEL_TYPE_PUBLIC:
            case CHANNEL_TYPE_PRIVATE:
                self::unsubscribeChannel($pushServer, $connection, $channel, $channelType);
                return;
            case CHANNEL_TYPE_PRESENCE:
                $userData = json_decode($request['data']['channel_data'], true);
                if (!$userData || !isset($userData['user_id'])) {
                    $pushServer->error($connection,null, 'Bad channel_data');
                    return;
                }
                self::unsubscribeChannel($pushServer, $connection, $channel, $userData['user_id']);
                break;
            default:
                $pushServer->error($connection, null, 'Bad channel_type');
                return;
        }
    }

    /**
     * 客户端取消订阅channel
     * @param Server $server
     * @param TcpConnection $connection
     * @param string $channel
     * @param string $type
     * @param string|null $uid
     * @return void
     */
    public static function unsubscribeChannel(Server $server, TcpConnection $connection, string $channel, string $type, ?string $uid = null): void
    {
        try {
            $appKey = $server->_getConnectionProperty($connection, 'appKey');
            $channels = $server->_getConnectionProperty($connection, 'channels');

            $channelType = $server->getStorage()->hGet($key = $server->_getChannelStorageKey($appKey, $channel), 'type');
            if ($type !== $channelType) {
                $server->error($connection, null, 'Bad channel_type');
                return;
            }

            if ($type === CHANNEL_TYPE_PRESENCE and $uid !== null) {
                if (!$server->getStorage()->exists($userKey = $server->_getUserStorageKey($appKey, $channel, $uid))) {
                    $server->error($connection, null, 'Bad user_id');
                    return;
                }
                $refCount = $server->getStorage()->hIncrBy($userKey, 'ref_count', -1);
                if ($refCount <= 0) {
                    $server->getStorage()->del($key);
                    $server->getStorage()->del($userKey);
                }
                // {"event":"pusher_internal:member_removed","data":"{\"user_id\":\"14884657801\"}","channel":"presence-channel"}
                $server->publishToClients($appKey, $channel, EVENT_MEMBER_REMOVED,
                    json_encode([
                        'id'      => uuid(),
                        'user_id' => $uid
                    ], JSON_UNESCAPED_UNICODE)
                );
                // PUSH_SERVER_EVENT_MEMBER_REMOVED 用户移除事件
                HookServer::publish(PUSH_SERVER_EVENT_MEMBER_REMOVED, [
                    'id'      => uuid(),
                    'app_key' => $appKey,
                    'channel' => $channel,
                    'user_id' => $uid,
                    'time_ms' => microtime(true)
                ]);
            }
            if($server->getStorage()->hIncrBy($key = $server->_getChannelStorageKey($appKey, $channel), 'subscription_count', -1) <= 0){
                $server->getStorage()->del($key);
                $channelVacated = true;
            }
            $server->_unsetConnection($connection, $appKey, $channel);
            unset($channels[$channel]);
            $server->_setConnectionProperty($connection, 'channels', $channels);
            /**
             * @private-channel:{"event":"pusher_internal:unsubscription_succeeded","data":"{}","channel":"my-channel"}
             * @public-channel:{"event":"pusher_internal:unsubscription_succeeded","data":"{}","channel":"my-channel"}
             * @presence-channel:{"event":"pusher_internal:unsubscription_succeeded","data":"{}","channel":"my-channel"}
             **/
            $server->send($connection, $channel, EVENT_UNSUBSCRIPTION_SUCCEEDED, '{}');

            if($channelVacated ?? false){
                // PUSH_SERVER_EVENT_CHANNEL_VACATED 通道移除事件
                HookServer::publish(PUSH_SERVER_EVENT_CHANNEL_VACATED, [
                    'id'      => uuid(),
                    'app_key' => $appKey,
                    'channel' => $channel,
                    'time_ms' => microtime(true)
                ]);
            }
        }catch (RedisException $exception){
            error_log("{$exception->getMessage()}\n");
        }
    }
}