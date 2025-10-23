<?php declare(strict_types=1);
/**
 * @author workbunny/Chaz6chez
 * @email chaz6chez1993@outlook.com
 */

namespace Workbunny\WebmanPushServer\Channels;

use InvalidArgumentException;
use support\Redis;
use Workerman\Redis\Client;

class RedisChannel implements ChannelInterface
{
    /**
     * @var Client
     */
    protected Client $_subClient;

    /**
     * @var \Redis
     */
    protected \Redis $_pubClient;

    /**
     * @var string
     */
    protected string $_redisChannel;

    /**
     * @param string|null $redisChannel
     * @throws \RedisException
     * @throws \Throwable
     */
    public function __construct(?string $redisChannel)
    {
        $this->_redisChannel = $redisChannel;
        if (!$config = config('redis')["plugin.workbunny.webman-push-server.$redisChannel"] ?? []) {
            throw new InvalidArgumentException("Redis channel [$redisChannel] not found. ");
        }
        $client = new Client(sprintf('redis://%s:%s', $config['host'], $config['port']), $config['options'] ?? []);
        $client->connect();
        if ($passport = $config['password'] ?? null) {
            $client->auth($passport);
        }
        if ($database = $config['database'] ?? 0) {
            $client->select($database);
        }
        $this->_subClient = $client;
        $this->_pubClient = Redis::connection($redisChannel)->client();
    }

    /** @inheritdoc  */
    public function unsubscribe(): mixed
    {
        $this->_subClient->close();
        return true;
    }

    /** @inheritdoc  */
    public function subscribe(string $channels, callable|array|\Closure $cb): mixed
    {
        $this->_subClient->subscribe($channels, $cb);
        return true;
    }

    /** @inheritdoc  */
    public function publish(string $channel, string $message): bool|int
    {
        return $this->_pubClient->publish($channel, $message);
    }
}