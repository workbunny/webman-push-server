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
use Workbunny\WebmanPushServer\Traits\ConnectionsMethods;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Workerman\Worker;

class ApiServer
{
    use ConnectionsMethods;

    public function __construct()
    {
        static::setStatisticsInterval(static::getConfig('traffic_statistics_interval', 0));
        // 加载中间件
        if ($middlewares = \config('plugin.workbunny.webman-push-server.middleware.api-server', [])) {
            $mid = [];
            foreach ($middlewares as $middleware) {
                if (is_callable($middleware)) {
                    $mid[] = $middleware;
                }
            }
            if ($mid) {
                ApiRoute::middleware(ApiRoute::TAG_ROOT, $mid);
            }
        }
    }

    /**
     * 获取配置
     *
     * @param string $key
     * @param mixed|null $default
     * @param bool $getBase
     * @return mixed
     */
    public static function getConfig(string $key, mixed $default = null, bool $getBase = false): mixed
    {
        return \config(
            ($getBase ?
                'plugin.workbunny.webman-push-server.app.' :
                'plugin.workbunny.webman-push-server.app.api-server.') .
            $key, $default
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
        $response->withHeader('SocketId', static::getConnectionProperty($connection, 'socketId'));
        static::setSendBytesStatistics($connection, (string)$response);
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
    public function onConnect(TcpConnection $connection): void
    {
        static::setConnectionProperty($connection, 'socketId', $socketId = static::createSocketId());
        static::setConnection('', $socketId, $connection);
    }

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
        self::setRecvBytesStatistics($connection, (string)$data);
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
        ), $data, $params, $connection);
        if (!$response instanceof Response) {
            $this->send(\Workbunny\WebmanPushServer\response(500, 'Server Error.'), $connection, $data);
            return;
        }
        $this->send($response, $connection, $data);
    }

    /**
     * @param TcpConnection $connection
     * @return void
     */
    public function onClose(TcpConnection $connection): void
    {
        if ($socketId = static::getConnectionProperty($connection, 'socketId')) {
            static::unsetConnection('', $socketId);
        }
    }
}