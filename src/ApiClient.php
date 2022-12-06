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
}