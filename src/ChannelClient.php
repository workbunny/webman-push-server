<?php declare(strict_types=1);

namespace Workbunny\WebmanPushServer;

use Exception;

class ChannelClient extends \Channel\Client
{
    /**
     * @param $events
     * @param $data
     * @param bool $is_loop
     * @return bool|null
     * @throws Exception
     */
    public static function publish($events, $data , $is_loop = false): ?bool
    {
        $type = $is_loop ? 'publishLoop' : 'publish';
        return self::sendAnyway(array('type' => $type, 'channels' => (array)$events, 'data' => $data));
    }

    /**
     * @param $data
     * @return bool|null
     * @throws Exception
     */
    protected static function sendAnyway($data): ?bool
    {
        self::connect(self::$_remoteIp, self::$_remotePort);
        $body = serialize($data);
        if (self::$_isWorkermanEnv) {
            return self::$_remoteConnection->send($body);
        } else {
            throw new Exception('Not workerman env. ');
        }
    }
}