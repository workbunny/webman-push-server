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
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Workerman\Worker;

class ApiService implements ServerInterface
{
    /**
     * @var null|mixed
     */
    protected $_buffer = null;

    /**
     * @param mixed|null $buffer
     */
    public function setBuffer($buffer): void
    {
        $this->_buffer = $buffer;
    }

    /**
     * @return mixed|null
     */
    public function getBuffer()
    {
        return $this->_buffer;
    }

    /**
     * 初始化
     */
    public function __construct()
    {
        ApiRoute::initCollector();
        ApiRoute::initRoutes();
        ApiRoute::initDispatcher();
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

    /**
     * TODO 单元测试
     * @param mixed $request
     * @param TcpConnection|null $connection
     * @return void
     */
    public function execute($request, ?TcpConnection $connection = null): void
    {
        if(!$request instanceof Request){
            $this->send(\Workbunny\WebmanPushServer\response(400, 'Bad Request. '), $connection);
            return;
        }
        $res = ApiRoute::getDispatcher()->dispatch($request->method(), $request->path());
        $handler = $res[1] ?? null;
        $params = $res[2] ?? [];
        if(!$handler instanceof Closure) {
            $this->send(\Workbunny\WebmanPushServer\response(404, 'Not Found'), $connection);
            return;
        }
        $response = call_user_func(array_reduce(
            array_reverse(ApiRoute::getMiddlewares(ApiRoute::getMiddlewareTag($handler), $request->method())),
            function (Closure $carry, Closure $pipe) {
                return function (...$arguments) use ($carry, $pipe) {
                    return $pipe($carry, ...$arguments);
                };
            },
            function (...$arguments) use ($handler) {
                return $handler(...$arguments);
            }
        ), Server::getServer(), $request, $params);
        if(!$response instanceof Response){
            $this->send(\Workbunny\WebmanPushServer\response(500, 'Server Error'), $connection);
            return;
        }
        $this->send($response, $connection);
    }

    /**
     * @param Response $response
     * @param TcpConnection|null $connection debug模式下传入null
     * @return void
     */
    public function send(Response $response, ?TcpConnection $connection = null): void
    {
        $response->withHeader('Content-Type', 'application/json');
        $response->withHeader('Server', 'workbunny/webman-push-server');
        $response->withHeader('Version', Server::$version);
        if(Server::isDebug()){
            $this->setBuffer($response);
            return;
        }
        $connection->send($response);
    }

    /** @inheritDoc */
    public function onWorkerStart(Worker $worker): void{}

    /** @inheritDoc */
    public function onWorkerStop(Worker $worker): void{}

    /** @inheritDoc */
    public function onConnect(TcpConnection $connection): void{}

    /** @inheritDoc */
    public function onMessage(TcpConnection $connection, $data): void
    {
        $this->execute($data, $connection);
    }

    /** @inheritDoc */
    public function onClose(TcpConnection $connection): void{}
}