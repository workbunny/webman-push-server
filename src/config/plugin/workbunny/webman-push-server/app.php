<?php
declare(strict_types=1);

use const Workbunny\WebmanPushServer\PUSH_SERVER_EVENT_CHANNEL_OCCUPIED;
use const Workbunny\WebmanPushServer\PUSH_SERVER_EVENT_CHANNEL_VACATED;
use const Workbunny\WebmanPushServer\PUSH_SERVER_EVENT_CLIENT_EVENT;
use const Workbunny\WebmanPushServer\PUSH_SERVER_EVENT_MEMBER_ADDED;
use const Workbunny\WebmanPushServer\PUSH_SERVER_EVENT_MEMBER_REMOVED;
use const Workbunny\WebmanPushServer\PUSH_SERVER_EVENT_SERVER_EVENT;

return [
    'enable'      => true,
    'debug'       => true,
    'push-server' => [
        'redis_channel' => 'default',
        'ws_host'       => 'websocket://0.0.0.0:3131',
        'api_host'      => 'http://0.0.0.0:3232',
        'apps_query'   => function (?string $appKey, ?string $appId = null): array {
            $apps = config('plugin.workbunny.webman-push-server.app.push-server.apps', []);
            if($appId !== null){
                foreach ($apps as $app){
                    if($app['app_id'] === $appId){
                        return $app;
                    }
                }
                return [];
            }
            return config('plugin.workbunny.webman-push-server.push-server.app.apps', [])[$appKey] ?? [];
        },
        'apps'          => [
            'APP_KEY_TO_REPLACE' => [
                'app_id'     => 'APP_ID_TO_REPLACE',
                'app_key'    => 'APP_KEY_TO_REPLACE',
                'app_secret' => 'APP_SECRET_TO_REPLACE',
            ]
        ],
    ],
    'hook-server' => [
        'redis_channel'  => 'default',
        'queue_key'      => 'workbunny:webman-push-server:webhook-stream',
        'prefetch_count' => 5,
        'queue_limit'    => 4096,
        'webhook_url'    => 'http://127.0.0.1:8787/plugin/workbunny/webman-push-server/webhook',
        'webhook_secret' => 'YOUR_WEBHOOK_SECRET',
        'events'         => [
            PUSH_SERVER_EVENT_MEMBER_ADDED, PUSH_SERVER_EVENT_MEMBER_REMOVED,
            PUSH_SERVER_EVENT_CLIENT_EVENT, PUSH_SERVER_EVENT_SERVER_EVENT,
            PUSH_SERVER_EVENT_CHANNEL_OCCUPIED, PUSH_SERVER_EVENT_CHANNEL_VACATED,
        ],
    ],
];