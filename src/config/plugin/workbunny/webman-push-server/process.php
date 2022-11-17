<?php
declare(strict_types=1);

use Workbunny\WebmanPushServer\Server;

return [
    'main_server' => [
        'handler'     => Server::class,
        'listen'      => config('plugin.workbunny.webman-push-server.app.host'),
        'count'       => cpu_count() * 2,
        'reloadable'  => false, // 执行reload不重启
        'constructor' => [
            'config' => config('plugin.workbunny.webman-push-server.app'),
        ]
    ]
];