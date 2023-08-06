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
use Workbunny\WebmanPushServer\HookServer;
use Workbunny\WebmanPushServer\Server;
use Workerman\Connection\TcpConnection;
use function Workbunny\WebmanPushServer\uuid;
use const Workbunny\WebmanPushServer\CHANNEL_TYPE_PRESENCE;
use const Workbunny\WebmanPushServer\CHANNEL_TYPE_PRIVATE;
use const Workbunny\WebmanPushServer\CHANNEL_TYPE_PUBLIC;
use const Workbunny\WebmanPushServer\EVENT_MEMBER_ADDED;
use const Workbunny\WebmanPushServer\EVENT_SUBSCRIPTION_SUCCEEDED;
use const Workbunny\WebmanPushServer\PUSH_SERVER_EVENT_CHANNEL_OCCUPIED;
use const Workbunny\WebmanPushServer\PUSH_SERVER_EVENT_MEMBER_ADDED;

class Subscribe extends AbstractEvent
{
    /**
     * @param Server $pushServer
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
    public function response(Server $pushServer, TcpConnection $connection, array $request): void
    {
        $channel = $request['data']['channel'] ?? '';
        $channelData = $request['data']['channel_data'] ?? [];
        $clientAuth = $request['data']['auth'] ?? '';
        $auth = self::auth(
            $appKey = $pushServer->_getConnectionProperty($connection, 'appKey'),
            Server::getConfig('apps_query')($appKey)['app_secret'],
            $pushServer->_getConnectionProperty($connection, 'socketId'),
            $channel,
            $channelData
        );
        // private- 和 presence- 开头的channel需要验证
        switch ($channelType = $pushServer->_getChannelType($channel)){
            case CHANNEL_TYPE_PRESENCE:
                if (!$channelData) {
                    $pushServer->error($connection, null, 'Empty channel_data');
                    return;
                }
                if ($clientAuth !== $auth) {
                    $pushServer->error($connection, null, 'Received invalid JSON '.$auth);
                    return;
                }
                if (!isset($channelData['user_id']) or !is_string($channelData['user_id'])) {
                    $pushServer->error($connection,null, 'Bad channel_data.user_id');
                    return;
                }
                if (!isset($channelData['user_info']) or !is_string($channelData['user_info'])) {
                    $pushServer->error($connection,null, 'Bad channel_data.user_info');
                    return;
                }
                self::subscribeChannel($pushServer, $connection, $channel, $channelType, $channelData['user_id'], $channelData['user_info']);
                break;
            case CHANNEL_TYPE_PRIVATE:
                if ($clientAuth !== $auth) {
                    $pushServer->error($connection,null, 'Received invalid JSON '.$auth);
                    return;
                }
                self::subscribeChannel($pushServer, $connection, $channel, $channelType);
                break;
            case CHANNEL_TYPE_PUBLIC:
                self::subscribeChannel($pushServer, $connection, $channel, $channelType);
                break;
            default:
                $pushServer->error($connection, null, 'Bad channel_type');
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
        if($channelData){
            ksort($channelData);
            return $appKey . ':' . hash_hmac('sha256', $socketId . ':' . $channel . ':' . json_encode($channelData, JSON_UNESCAPED_UNICODE), $appSecret);
        }
        return $appKey . ':' . hash_hmac('sha256', $socketId . ':' . $channel, $appSecret);
    }

    /**
     * 客户端订阅channel
     * @param Server $pushServer
     * @param TcpConnection $connection
     * @param string $channel
     * @param string $type = public | private | presence
     * @param string ...$params [$userId, $userInfo]
     * @return void
     */
    public static function subscribeChannel(Server $pushServer, TcpConnection $connection, string $channel, string $type, string ...$params): void
    {
        try {
            $appKey = $pushServer->_getConnectionProperty($connection, 'appKey');
            $socketId = $pushServer->_getConnectionProperty($connection, 'socketId');
            $channels = $pushServer->_getConnectionProperty($connection, 'channels');
            $userId = $params[0] ?? 'unknown';
            $userInfo = $params[1] ?? '{}';
            $isPresence = ($type === CHANNEL_TYPE_PRESENCE);
            $channelIsset = isset($channels[$channel]);
            $userIsset = ($channelIsset and $channels[$channel] === $userId);

            if(!$channelIsset){
                $channels[$channel] = $isPresence ? $userId : '';
                $pushServer->_setConnectionProperty($connection, 'channels', $channels);
                $pushServer->_setConnection($connection, $appKey, $channel);
            }

            $channelOccupied = Server::getStorage()->exists($key = $pushServer->_getChannelStorageKey($appKey, $channel));
            /** @see Server::$_storage */
            Server::getStorage()->hIncrBy($key,'subscription_count', $channelIsset ? 0 : 1);
            /** @see Server::$_storage */
            Server::getStorage()->hSet($key, 'type', $type);

            if($isPresence and !$userIsset) {
                /** @see Server::$_storage */
                Server::getStorage()->hIncrBy($key ,'user_count', 1);
                /** @see Server::$_storage */
                Server::getStorage()->hMSet($pushServer->_getUserStorageKey($appKey, $channel, $userId),[
                    'user_id'   => $userId,
                    'user_info' => $userInfo,
                    'socket_id' => $socketId
                ]);
                // {"event":"pusher_internal:member_added","data":"{"user_id":1488465780,"user_info":{"name":"123","sex":"1"}}","channel":"presence-channel"}
                $pushServer->publishToClients($appKey, $channel, EVENT_MEMBER_ADDED, [
                    'id'        => uuid(),
                    'user_id'   => $userId,
                    'user_info' => $userInfoArray = json_decode($userInfo, true)
                ], $socketId);
                // PUSH_SERVER_EVENT_MEMBER_ADDED 成员添加事件
                HookServer::instance()->publish(PUSH_SERVER_EVENT_MEMBER_ADDED, [
                    'id'        => uuid(),
                    'app_key'   => $appKey,
                    'channel'   => $channel,
                    'user_id'   => $userId,
                    'user_info' => $userInfoArray,
                    'time_ms'   => microtime(true)
                ]);
            }
            /**
             * @private-channel:{"event":"pusher_internal:subscription_succeeded","data":"{}","channel":"my-channel"}
             * @public-channel:{"event":"pusher_internal:subscription_succeeded","data":"{}","channel":"my-channel"}
             * @presence-channel:{"event":"pusher_internal:subscription_succeeded","data":"{\"presence\":{\"count\":2,\"ids\":[\"1488465780\",\"14884657802\"],\"hash\":{\"1488465780\":{\"name\":\"123\",\"sex\":\"1\"},\"14884657802\":{\"name\":\"123\",\"sex\":\"1\"}}}}","channel":"presence-channel"}
             */
            $pushServer->send(
                $connection,
                $channel,
                EVENT_SUBSCRIPTION_SUCCEEDED,
                $isPresence ? json_encode($pushServer->_getPresenceChannelDataForSubscribe($appKey, $channel), JSON_UNESCAPED_UNICODE) : '{}'
            );

            if(!$channelOccupied){
                // PUSH_SERVER_EVENT_CHANNEL_OCCUPIED 通道被创建事件
                HookServer::instance()->publish(PUSH_SERVER_EVENT_CHANNEL_OCCUPIED, [
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