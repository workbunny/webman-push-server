<?php
declare(strict_types=1);

use Workbunny\WebmanPushServer\Services\Apis;
use Workbunny\WebmanPushServer\Services\Hook;
use const Workbunny\WebmanPushServer\PUSH_SERVER_EVENT_CHANNEL_OCCUPIED;
use const Workbunny\WebmanPushServer\PUSH_SERVER_EVENT_CHANNEL_VACATED;
use const Workbunny\WebmanPushServer\PUSH_SERVER_EVENT_CLIENT_EVENT;
use const Workbunny\WebmanPushServer\PUSH_SERVER_EVENT_MEMBER_ADDED;
use const Workbunny\WebmanPushServer\PUSH_SERVER_EVENT_MEMBER_REMOVED;
use const Workbunny\WebmanPushServer\PUSH_SERVER_EVENT_SERVER_EVENT;

return [
    // api接口服务
    Apis::class => [
        'name'           => 'api-service',
        'count'          => cpu_count() * 2,
        'socket_name'    => 'http://0.0.0.0:3232',
        'context_option' => [],
        'extra'          => []
    ],
    // 钩子队列
    Hook::class => [
        'name'           => 'webhook-service',
        'count'          => cpu_count() * 2,
        'socket_name'    => null,
        'context_option' => [],
        'extra'          => [
            'events'         => [
                PUSH_SERVER_EVENT_MEMBER_ADDED, PUSH_SERVER_EVENT_MEMBER_REMOVED,
                PUSH_SERVER_EVENT_CLIENT_EVENT, PUSH_SERVER_EVENT_SERVER_EVENT,
                PUSH_SERVER_EVENT_CHANNEL_OCCUPIED, PUSH_SERVER_EVENT_CHANNEL_VACATED,
            ],
            'prefetch_count' => 5,
            'queue_limit'    => 4096,
            'hook_handler'   => function(Hook $hook, string $queue, string $group, array $data){
                return [
                    'hook_host'      => '127.0.0.1',
                    'hook_port'      => 8787,
                    'hook_uri'       => '/plugin/workbunny/webman-push-server/webhook',
                    'hook_secret'    => 'YOUR_WEBHOOK_SECRET',
                ];
            },
        ]

    ],
];