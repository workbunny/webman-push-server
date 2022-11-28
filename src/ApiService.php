<?php
declare(strict_types=1);

namespace Workbunny\WebmanPushServer;

use Closure;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Workerman\Worker;

class ApiService implements ServerInterface
{

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
            $connection->send(new Response(400, [], 'Bad Request. '));
            return;
        }
        $res = ApiRoute::getDispatcher()->dispatch($data->method(), $data->path());
        $handler = $res[1] ?? null;
        $params = $res[2] ?? [];
        if(!$handler instanceof Closure) {
            $connection->send(new Response(404, [], 'Not Found'));
            return;
        }
        $result = call_user_func(array_reduce(
            array_reverse(ApiRoute::getMiddlewares(ApiRoute::getMiddlewareTag($handler), $data->method())),
            function (Closure $next, Closure $handler) use ($data, $params) {
                return $handler($next, Server::getServer(), $data, $params);
            },
            $handler
        ));
        if(!$result instanceof Response){
            $connection->send(new Response(500, [], 'Server Error'));
            return;
        }
        $connection->send($result);
    }

    /** @inheritDoc */
    public function onWorkerStart(Worker $worker): void
    {
        ApiRoute::initRoutes();
        ApiRoute::initDispatcher();
    }

    /** @inheritDoc */
    public function onWorkerStop(Worker $worker): void{}

    /** @inheritDoc */
    public function onConnect(TcpConnection $connection): void{}

    /** @inheritDoc */
    public function onClose(TcpConnection $connection): void{}
}