<?php declare(strict_types=1);

use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;

return [
    // push server root middlewares
	'push-server' => [
        // 示例
        function (Closure $next, TcpConnection $connection, $data): void
        {
            $next($connection, $data);
        }
	],
    // api server root middlewares
    'api-server' => [
        // 示例
        function (Closure $next, Request $request, array $urlParams, TcpConnection $connection): Response
        {
            return $next($request, $urlParams, $connection);
        },
    ],
];