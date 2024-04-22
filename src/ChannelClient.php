<?php declare(strict_types=1);

namespace Workbunny\WebmanPushServer;

use Exception;

class ChannelClient extends \Channel\Client
{
    /**
     *
     * @return bool
     */
    public static function isChannelEnv(): bool
    {
        return !class_exists("\Workerman\Redis\Client", false);
    }

    /**
     * @param $ip
     * @param $port
     * @return void
     * @throws Exception
     */
    public static function connect($ip = '127.0.0.1', $port = 2206): void
    {
        if (self::isChannelEnv()) {
            parent::connect($ip, $port);
        } else {

        }
    }

    public static function on($event, $callback): void
    {
        if (self::isChannelEnv()) {
            parent::on($event, $callback);
        } else {
            // todo redis pub sub
        }
    }

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
            if (self::isChannelEnv()) {
                return self::$_remoteConnection->send($body);
            }
            // todo redis pub sub
        } else {
            throw new Exception('Not workerman env. ');
        }
    }
}