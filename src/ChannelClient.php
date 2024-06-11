<?php declare(strict_types=1);

namespace Workbunny\WebmanPushServer;

use Exception;
use support\Redis;
use Workerman\Redis\Client;

class ChannelClient extends \Channel\Client
{
    protected static ?Client $_redisClient = null;
    protected static ?string $_redisAddress = null;
    protected static array $_redisOptions = [];

    /**
     * @return bool
     */
    public static function isChannelEnv(): bool
    {
        return
            !class_exists("\Workerman\Redis\Client", false) and
            !self::getChannelSubstitution();
    }

    /**
     * @return false|string
     */
    public static function getChannelSubstitution(): false|string
    {
        return config('plugin.workbunny.webman-push-server.app.push-server.channel_substitution_enable', false);
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
            $channelName = self::getChannelSubstitution();
            if (!$config = config('redis.' . $channelName)) {
                throw new \InvalidArgumentException("Redis channel [$channelName] not found. ");
            }
            $ip = $config['host'];
            $port = $config['port'];
            self::$_redisClient = (new Client(self::$_redisAddress = "redis://$ip:$port", self::$_redisOptions = $options));
            self::$_redisClient->connect();
            if ($passport = $config['password'] ?? null) {
                self::$_redisClient->auth($passport);
            }
            if ($database = $config['database'] ?? 0) {
                self::$_redisClient->select($database);
            }
            self::connectRedis("redis://$ip:$port", $options);
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
        return self::sendAnywayByRedis($events, $data);
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

    /**
     * @param string $address
     * @param array $options
     * @return void
     */
    protected static function connectRedis(string $address, array $options = []): void
    {
        if (!self::$_redisClient) {
            self::$_redisClient = (new Client(self::$_redisAddress = $address, self::$_redisOptions = $options));
            self::$_redisClient?->connect();
        }
    }

    /**
     * @param array $events
     * @param mixed $data
     * @return bool
     * @throws Exception
     */
    protected static function sendAnywayByRedis(array $events, mixed $data): bool
    {
        $channelName = self::getChannelSubstitution();
        if (!config('redis.' . $channelName)) {
            throw new \InvalidArgumentException("Redis channel [$channelName] not found. ");
        }
        foreach ($events as $event) {
            Redis::connection($channelName)->client()->publish("workbunny:webman-push-server:$event", serialize($data));
        }
        return true;
    }
}