<?php
declare(strict_types=1);

use Workbunny\WebmanPushServer\ApiService;
use Workbunny\WebmanPushServer\HookServer;
use Workbunny\WebmanPushServer\Server;
use const Workbunny\WebmanPushServer\PUSH_SERVER_EVENT_CHANNEL_OCCUPIED;
use const Workbunny\WebmanPushServer\PUSH_SERVER_EVENT_CHANNEL_VACATED;
use const Workbunny\WebmanPushServer\PUSH_SERVER_EVENT_CLIENT_EVENT;
use const Workbunny\WebmanPushServer\PUSH_SERVER_EVENT_MEMBER_ADDED;
use const Workbunny\WebmanPushServer\PUSH_SERVER_EVENT_MEMBER_REMOVED;
use const Workbunny\WebmanPushServer\PUSH_SERVER_EVENT_SERVER_EVENT;

return [
    // 主服务
    'push-server' => [
        'handler'     => Server::class,
        'listen'      => config('plugin.workbunny.webman-push-server.app.ws_host'),
        'count'       => config('plugin.workbunny.webman-push-server.app.debug') ? 1 : cpu_count() * 2,
        'reloadable'  => false, // 执行reload不重启
        'constructor' => [
            'config' => config('plugin.workbunny.webman-push-server.app'),
        ],
        'services'    => [
            ApiService::class => [
                'handler'     => ApiService::class,
                'listen'      => config('plugin.workbunny.webman-push-server.app.api_host'),
                'constructor' => [],
            ]
        ]
    ],
    'hook-server' => [
        'handler'     => HookServer::class,
        'listen'      => null,
        'count'       => config('plugin.workbunny.webman-push-server.app.debug') ? 1 : cpu_count() * 2,
        'reloadable'  => false, // 执行reload不重启
        'constructor' => [
            'config' => [
                'events'       => [
                    PUSH_SERVER_EVENT_MEMBER_ADDED, PUSH_SERVER_EVENT_MEMBER_REMOVED,
                    PUSH_SERVER_EVENT_CLIENT_EVENT, PUSH_SERVER_EVENT_SERVER_EVENT,
                    PUSH_SERVER_EVENT_CHANNEL_OCCUPIED, PUSH_SERVER_EVENT_CHANNEL_VACATED,
                ],
                'queue_config' => [
                    'queue_key'      => 'workbunny:webman-push-server:webhook-stream',
                    'prefetch_count' => 5,
                    'queue_limit'    => 4096,
                ],
                'webhook_config' => [
                    'webhook_url'    => 'http://127.0.0.1:8787/plugin/workbunny/webman-push-server/webhook',
                    'webhook_secret' => 'YOUR_WEBHOOK_SECRET',
                ],
            ]
        ],
    ]
];