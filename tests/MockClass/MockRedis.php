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

}