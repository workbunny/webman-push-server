<?php declare(strict_types=1);
/**
 * @author workbunny/Chaz6chez
 * @email chaz6chez1993@outlook.com
 */

namespace Workbunny\WebmanPushServer\Traits;

use RedisException;
use support\Redis;
use const Workbunny\WebmanPushServer\CHANNEL_TYPE_PRESENCE;

trait StorageMethods
{
    /**
     *  channel信息结构:
     *
     *   app_{appKey1}:channel_{channel1}:info = [
     *       type               => 'presence', // 通道类型
     *       subscription_count => 0,          // 订阅数
     *       user_count         => 0,          // 用户数
     *   ]
     *
     *
     *  user信息结构:
     *
     *   app_{appKey1}:channel_{channel1}:uid_{uid1} = [
     *       user_id    => user_id,      // 用户id
     *       user_info  => json string,  // 用户信息json
     *       socket_id  => socketId      // 客户端id
     *   ]
     *
     * @var \Redis|null
     */
    protected static ?\Redis $_storageClient = null;

    /** @var string  */
    protected static string $storageRedisChannelKey = 'plugin.workbunny.webman-push-server.server-storage';

    /**
     * @return \Redis
     */
    public static function getStorageClient(): \Redis
    {
        if(!self::$_storageClient instanceof \Redis){
            self::$_storageClient =
                Redis::connection(self::$storageRedisChannelKey)->client();
        }
        return self::$_storageClient;
    }

    /**
     * 获取通道储存key
     *
     * @param string $appKey
     * @param string|null $channel
     * @return string
     */
    public static function getChannelStorageKey(string $appKey, ?string $channel = null): string
    {
        $channel = $channel !== null ? $channel : '*';
        return "workbunny:webman-push-server:appKey_$appKey:channel_$channel:info";
    }

    /**
     * 获取通道名称
     *
     * @param string $channelStorageKey
     * @return string
     */
    public static function getChannelName(string $channelStorageKey): string
    {
        $channelKey = explode(':', $channelStorageKey, 5)[3];
        return explode('_', $channelKey, 2)[1];
    }

    /**
     * 获取用户储存key
     *
     * @param string $appKey
     * @param string|null $channel
     * @param string|null $uid
     * @return string
     */
    public static function getUserStorageKey(string $appKey, ?string $channel = null, ?string $uid = null): string
    {
        $channel = $channel !== null ? $channel : '*';
        $uid = $uid !== null ? $uid : '*';
        return "workbunny:webman-push-server:appKey_$appKey:channel_$channel:uid_$uid";
    }

    /**
     * 获取用户id
     *
     * @param string $userStorageKey
     * @return string
     */
    public static function getUserId(string $userStorageKey): string
    {
        $userIdKey = explode(':', $userStorageKey, 5)[4];
        return explode('_', $userIdKey, 2)[1];
    }

    /**
     * @param string $appKey
     * @param string $channel
     * @return array[]
     * @throws RedisException
     */
    public static function getPresenceChannelDataForSubscribe(string $appKey, string $channel): array
    {
        $hash = [];
        $storage = self::getStorageClient();
        while(
            false !== ($keys = $storage->scan($iterator, self::getUserStorageKey($appKey, $channel),100))
        ) {
            foreach($keys as $key) {
                $result = $storage->hGetAll($key);
                $hash[$result['user_id']] = $result['user_info'];
            }
        }
        return [
            CHANNEL_TYPE_PRESENCE => [
                'count' => count($hash),
                'ids'   => array_keys($hash),
                'hash'  => $hash
            ]
        ];
    }
}