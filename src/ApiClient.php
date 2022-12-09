<?php

declare(strict_types=1);

namespace Workbunny\WebmanPushServer;

use Pusher\Pusher;
use Workbunny\WebmanPushServer\Events\Subscribe;

class ApiClient extends Pusher
{
    /**
     * @param string $appKey
     * @param string $appSecret
     * @param string $socketId
     * @param string $channel
     * @param array $channelData
     * @return string
     */
    public static function subscribeAuth(string $appKey, string $appSecret, string $socketId, string $channel, array $channelData = []): string
    {
        return Subscribe::auth($appKey, $appSecret, $socketId, $channel, $channelData);
    }

    /**
     * @param string $appKey
     * @param string $appSecret
     * @param string $httpMethod
     * @param string $httpPath
     * @param array $query
     * @return mixed
     */
    public static function routeAuth(string $appKey, string $appSecret, string $httpMethod, string $httpPath, array $query){
        return Server::isDebug() ? 'test' : self::build_auth_query_params($appKey, $appSecret, $httpMethod, $httpPath, $query)['auth_signature'];
    }
}