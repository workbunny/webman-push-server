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

use Workbunny\WebmanPushServer\ApiService;
use Workbunny\WebmanPushServer\HookServer;
use Workbunny\WebmanPushServer\Server;

return [
    // 推送服务器
    'push-server' => [
        'handler'    => Server::class,
        'listen'     => config('plugin.workbunny.webman-push-server.app.push-server.ws_host'),
        'count'      => cpu_count() * 2,
        'reloadable' => false, // 执行reload不重启
        'reusePort'  => true,
        'service'    => [ // 区别于 webman 的 services 配置
            // API子服务
            ApiService::class => [
                'handler' => ApiService::class,
                'listen'  => config('plugin.workbunny.webman-push-server.app.push-server.api_host'),
                'context' => [],
            ]
        ]
    ],
    // hook钩子消费者
    'hook-server' => [
        'handler'     => HookServer::class,
        'listen'      => null,
        'count'       => cpu_count() * 2,
        'reloadable'  => false, // 执行reload不重启
        'reusePort'   => true
    ]
];