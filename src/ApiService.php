<?php
declare(strict_types=1);

namespace Workbunny\WebmanPushServer;

use Closure;
use FastRoute\DataGenerator\GroupCountBased;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Workerman\Worker;

/**
 * @link RouteCollector::get()
 * @method static void get(string $route, Closure $handler, Closure ...$middlewares)
 *
 * @link RouteCollector::post()
 * @method static void post(string $route, Closure $handler, Closure ...$middlewares)
 *
 * @link RouteCollector::put()
 * @method static void put(string $route, Closure $handler, Closure ...$middlewares)
 *
 * @link RouteCollector::patch()
 * @method static void patch(string $route, Closure $handler, Closure ...$middlewares)
 *
 * @link RouteCollector::head()
 * @method static void head(string $route, Closure $handler, Closure ...$middlewares)
 *
 * @link RouteCollector::delete()
 * @method static void delete(string $route, Closure $handler, Closure ...$middlewares)
 *
 * @link RouteCollector::addGroup()
 * @method static void addGroup(string $prefix, Closure $callback, Closure ...$middlewares)
 *
 * @link RouteCollector::addRoute()
 * @method static void addRoute($httpMethod, string $route, Closure $handler, Closure ...$middlewares)
 *
 * @desc method = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS']
 * @method static void any(string $route, Closure $handler, Closure ...$middlewares)
 */
class ApiService implements ServerInterface
{

    /**
     * @var RouteCollector|null
     */
    protected static ?RouteCollector $_collector = null;

    /**
     * @var Dispatcher|null
     */
    protected static ?Dispatcher $_dispatcher = null;

    /**
     * @var bool
     */
    protected static bool $_groupStatus = false;

    /**
     * @var array = [
     *       #group | spl_object_hash(handler) => [
     *          method => [
     *              [Closure 1, Closure 2, Closure 3]
     *          ]
     *      ]
     * ]
     */
    protected static array $_middlewares = [];

    /**
     * 简单的中间件
     * @param string $tag
     * @param array $middlewares
     * @param array|null $methods
     * @return void
     */
    public static function middleware(string $tag, array $middlewares, ?array $methods = null): void
    {
        foreach ($middlewares as $middleware){
            if($methods){
                foreach ($methods as $method){
                    self::$_middlewares[$tag][$method][] = $middleware;
                }
            }else{
                self::$_middlewares[$tag][] = $middleware;
            }
        }
    }

    /**
     * @param Closure $handler
     * @return string
     */
    public static function getMiddlewareTag(Closure $handler): string
    {
        return spl_object_hash($handler);
    }

    /**
     * @param string|null $tag
     * @param string|null $method
     * @return array
     */
    public static function getMiddlewares(?string $tag = null, ?string $method = null): array
    {
        $middlewares = self::$_middlewares;
        if($tag !== null){
            $middlewares = $middlewares[$tag] ?? [];
        }
        if($method !== null){
            $middlewares = $middlewares[$method] ?? [];
        }
        return $middlewares;
    }

    /**
     * @param array|string $httpMethod
     * @param string $route
     * @param Closure $handler
     * @param Closure ...$middlewares
     * @return void
     * @see RouteCollector::addRoute()
     */
    public static function route($httpMethod, string $route, Closure $handler, Closure ...$middlewares): void
    {
        $methods = is_array($httpMethod) ? $httpMethod : [$httpMethod];
        self::middleware(self::getMiddlewareTag($handler), self::$_groupStatus ? self::getMiddlewares('#group') : $middlewares, $methods);
        self::$_collector->addRoute($methods, $route, $handler);
    }

    /**
     * @param string $prefix
     * @param Closure $callback
     * @param Closure ...$middlewares
     * @return void
     * @see  RouteCollector::addGroup()
     */
    public static function group(string $prefix, Closure $callback, Closure ...$middlewares): void
    {
        self::$_groupStatus = true;
        self::middleware('#group', $middlewares);
        self::$_collector->addGroup(...func_get_args());
        unset(self::$_middlewares['#group']);
        self::$_groupStatus= false;

    }

    /** @inheritDoc */
    public static function getConfig(string $key, $default = null)
    {
        return Server::getConfig($key, $default);
    }

    /** @inheritDoc */
    public static function getStorage(): \Redis
    {
        return Server::getStorage();
    }

    /** @inheritDoc */
    public function onMessage(TcpConnection $connection, $data): void
    {
        if(!$data instanceof Request){
            $connection->close(new Response(400, [], 'Bad Request. '));
            return;
        }
        $res = self::$_dispatcher->dispatch($data->method(), $data->path());
        if(!$res){
            $connection->send(new Response(404, [], 'Not Found'));
        }

        $result = array_reduce(
            array_reverse(self::getMiddlewares(self::getMiddlewareTag($res[1]), $data->method())),
            function (Closure $next, Closure $handler) use ($data, $res) {
                return $handler($next, $this, $data, $res[2]);
            },
            $res[1]
        );

        if(!$result instanceof Response){
            $connection->send(new Response(500, [], 'Server Error'));
            return;
        }
        $connection->send($result);
    }

    /** @inheritDoc */
    public function onWorkerStart(Worker $worker): void
    {
        self::$_collector = new RouteCollector(
            new Std(), new GroupCountBased()
        );
//        if(\is_file($file = __DIR__ . '/config/plugin/workbunny/webman-push-server/apis.php')){
//            require_once $file;
//        }
        if(\is_file($file = \config_path() . '/plugin/workbunny/webman-push-server/apis.php')){
            require_once $file;
        }
        self::$_dispatcher = new Dispatcher\GroupCountBased(self::$_collector->getData());
    }

    /** @inheritDoc */
    public function onWorkerStop(Worker $worker): void{}

    /** @inheritDoc */
    public function onConnect(TcpConnection $connection): void{}

    /** @inheritDoc */
    public function onClose(TcpConnection $connection): void{}

    /**
     * @param $name
     * @param $arguments
     * @return void
     */
    public static function __callStatic($name, $arguments)
    {
        switch (true){
            case $name === 'addGroup':
                self::group(...$arguments);
                return;
            case $name === 'addRoute':
                self::route(...$arguments);
                return;
            case $name === 'any':
                self::route(['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'], ...$arguments);
                return;
            default:
                self::route(strtoupper($name), ...$arguments);
        }
    }
}