<?php
/**
 * This file is part of workbunny.
 *
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    chaz6chez<chaz6chez1993@outlook.com>
 * @copyright chaz6chez<chaz6chez1993@outlook.com>
 * @link      https://github.com/workbunny/webman-push-server
 * @license   https://github.com/workbunny/webman-push-server/blob/main/LICENSE
 */
declare(strict_types=1);

namespace Workbunny\WebmanPushServer;

use Closure;
use FastRoute\DataGenerator\GroupCountBased;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std;
use Webman\Bootstrap;
use function config_path;
use function is_file;

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
class ApiRoute implements Bootstrap
{
    const TAG_GROUP = '#group';
    const TAG_ROOT  = '#root';

    /**
     * @var RouteCollector|null
     */
    protected static ?RouteCollector $_collector = null;

    /**
     * @var Dispatcher|null
     */
    protected static ?Dispatcher $_dispatcher = null;

    /**
     * @var string|null
     */
    protected static ?string $_groupPrefix = null;

    /**
     * @var array = [
     *      [
     *          'method'      => 'POST',
     *          'uri'         => '/apps/{appId}/channels'
     *          'handler'     => Closure function
     *      ],
     * ]
     */
    protected static array $_routes = [];

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

    /** @inheritDoc */
    public static function start($worker, $configPath = null): void
    {
        ApiRoute::initCollector();
        ApiRoute::initRoutes($configPath);
        ApiRoute::initDispatcher();
    }

    /**
     * @param null $configPath
     * @return void
     */
    public static function initRoutes($configPath = null): void
    {
        if (is_file($file = ($configPath ?: config_path()) . '/plugin/workbunny/webman-push-server/route.php')) {

            require_once $file;
        }
    }

    /**
     * @return void
     */
    public static function initCollector(): void
    {
        if (!self::$_collector) {
            self::$_collector =  new RouteCollector(
                new Std(), new GroupCountBased()
            );
        }
    }

    /**
     * @return RouteCollector|null
     */
    public static function getCollector(): ?RouteCollector
    {
        return self::$_collector;
    }

    /**
     * @return void
     */
    public static function initDispatcher(): void
    {
        if (!self::$_dispatcher) {
            self::$_dispatcher = new Dispatcher\GroupCountBased(self::getCollector()->getData());
        }
    }

    /**
     * @return Dispatcher|null
     */
    public static function getDispatcher(): ?Dispatcher
    {
        return self::$_dispatcher;
    }

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
     * @return array
     */
    public static function getRoutes(): array
    {
        return self::$_routes;
    }

    /**
     * @param array|string $httpMethod
     * @param string $route
     * @param Closure $handler
     * @param Closure ...$middlewares
     * @return void
     * @see RouteCollector::addRoute()
     */
    public static function route(array|string $httpMethod, string $route, Closure $handler, Closure ...$middlewares): void
    {
        $methods = is_array($httpMethod) ? $httpMethod : [$httpMethod];
        foreach ($methods as $method){
            self::$_routes[] = [
                'method'      => $method,
                'uri'         => self::$_groupPrefix ? self::$_groupPrefix . $route : $route,
                'handler'     => $handler
            ];
        }
        self::middleware(
            self::getMiddlewareTag($handler),
            self::getMiddlewares(self::TAG_ROOT) + (self::$_groupPrefix !== null ?
                self::getMiddlewares(self::TAG_GROUP) :
                $middlewares),
            $methods
        );
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
        self::$_groupPrefix = $prefix;
        self::middleware(self::TAG_GROUP, $middlewares);
        self::$_collector->addGroup(...func_get_args());
        unset(self::$_middlewares[self::TAG_GROUP]);
        self::$_groupPrefix = null;
    }

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