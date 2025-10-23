<?php declare(strict_types=1);
/**
 * @author workbunny/Chaz6chez
 * @email chaz6chez1993@outlook.com
 */
namespace Workbunny\WebmanPushServer\Storages;

use Workbunny\WebmanPushServer\Exceptions\StorageException;

interface StorageInterface
{

    /**
     * @link \Redis::keys()
     * @param string $pattern
     * @return array|false
     * @throws StorageException
     */
    public function keys(string $pattern): array|false;

    /**
     * @link \Redis::exists()
     * @param string $key
     * @return bool|int
     * @throws StorageException
     */
    public function exists(string $key): bool|int;

    /**
     * @link \Redis::del()
     * @param string $key1
     * @param string ...$otherKeys
     * @return bool|int
     * @throws StorageException
     */
    public function del(string $key1, string ...$otherKeys): bool|int;

    /**
     * @link \Redis::scan()
     * @param $iterator
     * @param string $pattern
     * @param int $count
     * @return mixed
     * @throws StorageException
     */
    public function scan(&$iterator, string $pattern, int $count = 0): mixed;

    /**
     * @link \Redis::hSet()
     * @param string $key
     * @param string $hashKey
     * @param mixed $value
     * @return bool|int
     * @throws StorageException
     */
    public function hSet(string $key, string $hashKey, mixed $value): bool|int;

    /**
     * @link \Redis::hGet()
     * @param string $key
     * @param string $hashKey
     * @return mixed
     * @throws StorageException
     */
    public function hGet(string $key, string $hashKey): mixed;

    /**
     * @link \Redis::hMSet()
     * @param string $key
     * @param array $hashKeys
     * @return bool
     * @throws StorageException
     */
    public function hMSet(string $key, array $hashKeys): bool;

    /**
     * @link \Redis::hMGet()
     * @param string $key
     * @param array $hashKeys
     * @return mixed
     * @throws StorageException
     */
    public function hMGet(string $key, array $hashKeys): mixed;

    /**
     * @link \Redis::hGetAll()
     * @param string $key
     * @return mixed
     * @throws StorageException
     */
    public function hGetAll(string $key): mixed;

    /**
     * @link \Redis::hIncrBy()
     * @param string $key
     * @param string $hashKey
     * @param int $value
     * @return mixed
     * @throws StorageException
     */
    public function hIncrBy(string $key, string $hashKey, int $value): mixed;



}