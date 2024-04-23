<?php declare(strict_types=1);

namespace Workbunny\WebmanPushServer;

use Exception;
use Workerman\Redis\Client;

class ChannelClient extends \Channel\Client
{
    protected static ?Client $_redisClient = null;
    /**
     *
     * @return bool
     */
    public static function isChannelEnv(): bool
    {
        return !class_exists("\Workerman\Redis\Client", false);
    }

    /**
     * @param string $ip
     * @param int $port
     * @param array $options
     * @return void
     * @throws Exception
     */
    public static function connect($ip = '127.0.0.1', $port = 2206, array $options = []): void
    {
        if (self::isChannelEnv()) {
            parent::connect($ip, $port);

        } else {
            self::$_redisClient = (new Client("redis://$ip:$port", $options));
            self::$_redisClient?->connect();
        }
    }

    /**
     * @param string $event
     * @param callable $callback
     * @return void
     * @throws Exception
     */
    public static function on($event, $callback): void
    {
        if (self::isChannelEnv()) {
            parent::on($event, $callback);
        } else {
            self::$_redisClient?->subscribe("workbunny:webman-push-server:$event", function ($channel, $message) use ($callback) {
                call_user_func($callback, unserialize($message));
            });
        }
    }

    /**
     * @param string $events
     * @param $data
     * @param bool $is_loop
     * @return bool|null
     * @throws Exception
     */
    public static function publish($events, $data, $is_loop = false): ?bool
    {
        $events = (array)$events;
        $type = $is_loop ? 'publishLoop' : 'publish';
        if (self::isChannelEnv()) {
            return self::sendAnyway(array('type' => $type, 'channels' => $events, 'data' => $data));
        }
        foreach ($events as $event) {
            self::$_redisClient?->publish("workbunny:webman-push-server:$event", serialize($data));
        }
        return true;
    }

    /**
     * @inheritDoc
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