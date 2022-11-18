<?php
declare(strict_types=1);

namespace Workbunny\WebmanPushServer\Apis;

use Workbunny\WebmanPushServer\Server;
use Workerman\Connection\TcpConnection;

abstract class AbstractApis
{
    /**
     * @var string[]
     */
    protected static array $_apis = [
        'batch_events' => BatchEvents::class,
        'events'       => Events::class,
        'channels'     => Channels::class,
    ];

    /**
     * @var AbstractApis[]
     */
    protected static array $_apisObj = [];

    /**
     * @param string $api
     * @return AbstractApis|null
     */
    public static function factory(string $api): ?AbstractApis
    {
        if(self::exists($api)){
            return self::$_apisObj[$api] ?? (self::$_apisObj[$api] = new $api);
        }
        return null;
    }

    /**
     * @param string $api
     * @param string $apiClass
     * @return void
     */
    final public static function register(string $api, string $apiClass): void
    {
        if(self::exists($api)){
            throw new \InvalidArgumentException("API $api already exists. ");
        }
        if(!(new $apiClass) instanceof AbstractApis){
            throw new \InvalidArgumentException("Invalid API class $apiClass. ");
        }
        self::$_apisObj[$api] = $apiClass;
    }

    /**
     * @param string $api
     * @return bool
     */
    final public static function exists(string $api): bool
    {
        return isset(self::$_apis[$api]);
    }

    /**
     * 响应
     * @param string $appKey
     * @param Server $pushServer
     * @param TcpConnection $connection
     * @param mixed $data
     * @return void
     */
    abstract public function response(string $appKey, Server $pushServer, TcpConnection $connection, $data): void;
}