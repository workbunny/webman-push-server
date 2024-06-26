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
use function config;

class ApiServer
{
    /**
     * 初始化
     */
    public function __construct()
    {
        ApiRoute::initCollector();
        ApiRoute::initRoutes();
        ApiRoute::initDispatcher();
    }

    /**
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     */
    public static function getConfig(string $key, mixed $default = null): mixed
    {
        return config(
            'plugin.workbunny.webman-push-server.app.api-server.' . $key, $default
        );
    }

    /**
     * @param Response $response
     * @param TcpConnection $connection debug模式下传入null
     * @param Request|null $request
     * @return void
     */
    public function send(Response $response, TcpConnection $connection, ?Request $request = null): void
    {
        $response->withHeader('Content-Type', 'application/json');
        $response->withHeader('Server', 'workbunny/webman-push-server');
        $response->withHeader('Version', PushServer::$version);
        if ($request) {
            $keepAlive = $request->header('connection');
            if (
                ($keepAlive === null and $request->protocolVersion() === '1.1')
                or $keepAlive === 'keep-alive'
                or $keepAlive === 'Keep-Alive'
            ) {
                $connection->send($response);
                return;
            }
        }
        $connection->close($response);
    }

    /**
     * @param Worker $worker
     * @return void
     */
    public function onWorkerStart(Worker $worker): void{}

    /**
     * @param Worker $worker
     * @return void
     */
    public function onWorkerStop(Worker $worker): void{}

    /**
     * @param TcpConnection $connection
     * @return void
     */
    public function onConnect(TcpConnection $connection): void{}

    /**
     * @param TcpConnection $connection
     * @param $data
     * @return void
     */
    public function onMessage(TcpConnection $connection, $data): void
    {
        if (!$data instanceof Request){
            $this->send(\Workbunny\WebmanPushServer\response(400, 'Bad Request.'), $connection);
            return;
        }
        $res = ApiRoute::getDispatcher()->dispatch($data->method(), $data->path());
        $handler = $res[1] ?? null;
        $params = $res[2] ?? [];
        if(!$handler instanceof Closure) {
            $this->send(\Workbunny\WebmanPushServer\response(404, 'Not Found.'), $connection, $data);
            return;
        }
        $response = call_user_func(array_reduce(
            array_reverse(ApiRoute::getMiddlewares(ApiRoute::getMiddlewareTag($handler), $data->method())),
            function (Closure $carry, Closure $pipe) {
                return function (...$arguments) use ($carry, $pipe) {
                    return $pipe($carry, ...$arguments);
                };
            },
            function (...$arguments) use ($handler) {
                return $handler(...$arguments);
            }
        ), $data, $params);
        if(!$response instanceof Response){
            $this->send(\Workbunny\WebmanPushServer\response(500, 'Server Error.'), $connection, $data);
            return;
        }
        $this->send($response, $connection, $data);
    }

    /**
     * @param TcpConnection $connection
     * @return void
     */
    public function onClose(TcpConnection $connection): void {}
}