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

use Workbunny\WebmanPushServer\ApiServer;
use Workbunny\WebmanPushServer\PushServer;

return [
    // 推送服务器
    'push-server' => [
        'handler'     => PushServer::class,
        'listen'      => 'websocket://0.0.0.0:' . config('plugin.workbunny.webman-push-server.app.push-server.port', 8002),
        'count'       => cpu_count(),
        'reloadable'  => false, // 执行reload不重启
        'reusePort'   => true,
    ],
    // api服务器
    'api-server' => [
        'handler'     => ApiServer::class,
        'listen'      => 'http://0.0.0.0:' . config('plugin.workbunny.webman-push-server.app.api-server.port', 8002),
        'count'       => cpu_count(),
        'reloadable'  => false, // 执行reload不重启
        'reusePort'   => true,
    ],
];