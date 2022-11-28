<?php
declare(strict_types=1);

use Workbunny\WebmanPushServer\ApiService;
use Workbunny\WebmanPushServer\HookServer;
use Workbunny\WebmanPushServer\Server;

return [
    // 主服务
    'push-server' => [
        'handler'     => Server::class,
        'workbunny'      => config('plugin.workbunny.webman-push-server.app.push-server.ws_host'),
        'count'       => config('plugin.workbunny.webman-push-server.app.debug') ? 1 : cpu_count() * 2,
        'reloadable'  => false, // 执行reload不重启
        'services'    => [
            ApiService::class => [
                'handler'     => ApiService::class,
                'listen'      => config('plugin.workbunny.webman-push-server.app.push-server.api_host'),
            ]
        ]
    ],
    'hook-server' => [
        'handler'     => HookServer::class,
        'listen'      => null,
        'count'       => config('plugin.workbunny.webman-push-server.app.debug') ? 1 : cpu_count() * 2,
        'reloadable'  => false, // 执行reload不重启
    ]
];