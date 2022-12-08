<?php
declare(strict_types=1);

namespace Tests\MockClass;

use function Workbunny\WebmanPushServer\uuid;

class MockRedis extends \Redis
{
    protected array $_storage = [];

    /**
     * @return array
     */
    public function getStorage(): array
    {
        return $this->_storage;
    }

    /**
     * @param array $storage
     */
    public function setStorage(array $storage): void
    {
        $this->_storage = $storage;
    }

    public function exists($key, ...$otherKeys)
    {
        return isset($this->_storage[$key]);
    }

    public function keys($pattern)
    {
        $result = [];
        $keys = array_keys($this->_storage);
        foreach ($keys as $key){
            if(fnmatch($pattern, $key)){
                $result[] = $key;
            }
        }
        return $result;
    }

    public function hIncrBy($key, $hashKey, $value)
    {
        $this->_storage[$key][$hashKey] = ($this->_storage[$key][$hashKey] ?? 0) + $value;
        return $this->_storage[$key][$hashKey];
    }

    public function hMSet($key, $hashKeys)
    {
        foreach ($hashKeys as $hashKey => $value){
            $this->_storage[$key][$hashKey] = $value;
        }
    }

    public function hMGet($key, $hashKeys)
    {
        $result = [];
        foreach ($hashKeys as $hashKey){
            $result[$hashKey] = $this->hGet($key, $hashKey);
        }
        return $result;
    }

    public function hSet($key, $hashKey, $value)
    {
        $this->_storage[$key][$hashKey] = $value;
    }

    public function hGet($key, $hashKey)
    {
        return $this->_storage[$key][$hashKey] ?? null;
    }

    public function del($key1, ...$otherKeys)
    {
        if($this->exists($key1)){
            unset($this->_storage[$key1]);
        }
        foreach ($otherKeys as $key){
            if($this->exists($key)){
                unset($this->_storage[$key]);
            }
        }
    }
}