<?php declare(strict_types=1);
/**
 * @author workbunny/Chaz6chez
 * @email chaz6chez1993@outlook.com
 */

namespace Workbunny\WebmanPushServer\Storages;

use support\Redis;
use Workbunny\WebmanPushServer\Exceptions\StorageException;

class RedisStorage implements StorageInterface
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
    protected ?\Redis $_client = null;

    /** @var string  */
    protected static string $storageRedisChannelKey = 'plugin.workbunny.webman-push-server.server-storage';

    /**
     * @throws \RedisException
     * @throws \Throwable
     */
    public function __construct()
    {
        $this->_client = Redis::connection(self::$storageRedisChannelKey)->client();
    }

    /** @inheritdoc  */
    public function keys(string $pattern): array|false
    {
        try {
            return $this->_client->keys($pattern);
        } catch (\RedisException $e) {
            throw new StorageException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /** @inheritdoc  */
    public function exists($key): bool|int
    {
        try {
            return $this->_client->exists($key);
        } catch (\RedisException $e) {
            throw new StorageException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /** @inheritdoc  */
    public function del($key1, ...$otherKeys): bool|int
    {
        try {
            return $this->_client->del($key1, ...$otherKeys);
        } catch (\RedisException $e) {
            throw new StorageException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /** @inheritdoc  */
    public function scan(&$iterator, string $pattern, $count = 0): mixed
    {
        try {
            return $this->_client->scan($iterator, $pattern, $count);
        } catch (\RedisException $e) {
            throw new StorageException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /** @inheritdoc  */
    public function hSet(string $key, string $hashKey, mixed $value): bool
    {
        try {
            return $this->_client->hSet($key, $hashKey, $value);
        } catch (\RedisException $e) {
            throw new StorageException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /** @inheritdoc  */
    public function hGet(string $key, string $hashKey): mixed
    {
        try {
            return $this->_client->hGet($key, $hashKey);
        } catch (\RedisException $e) {
            throw new StorageException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /** @inheritdoc  */
    public function hMSet(string $key, array $hashKeys): bool
    {
        try {
            return $this->_client->hMSet($key, $hashKeys);
        } catch (\RedisException $e) {
            throw new StorageException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /** @inheritdoc  */
    public function hMGet(string $key, array $hashKeys): mixed
    {
        try {
            return $this->_client->hMGet($key, $hashKeys);
        } catch (\RedisException $e) {
            throw new StorageException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /** @inheritdoc  */
    public function hGetAll(string $key): mixed
    {
        try {
            return $this->_client->hGetAll($key);
        } catch (\RedisException $e) {
            throw new StorageException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /** @inheritdoc  */
    public function hIncrBy(string $key, string $hashKey, int $value): mixed
    {
        try {
            return $this->_client->hIncrBy($key, $hashKey, $value);
        } catch (\RedisException $e) {
            throw new StorageException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
