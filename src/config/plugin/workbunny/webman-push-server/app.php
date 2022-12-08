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

use const Workbunny\WebmanPushServer\PUSH_SERVER_EVENT_CHANNEL_OCCUPIED;
use const Workbunny\WebmanPushServer\PUSH_SERVER_EVENT_CHANNEL_VACATED;
use const Workbunny\WebmanPushServer\PUSH_SERVER_EVENT_CLIENT_EVENT;
use const Workbunny\WebmanPushServer\PUSH_SERVER_EVENT_MEMBER_ADDED;
use const Workbunny\WebmanPushServer\PUSH_SERVER_EVENT_MEMBER_REMOVED;
use const Workbunny\WebmanPushServer\PUSH_SERVER_EVENT_SERVER_EVENT;

return [
    'enable'      => true,
    // 默认app_key配置
    'apps'        => [
        'workbunny' => [
            'app_id'     => '1',
            'app_key'    => 'workbunny',
            'app_secret' => 'U2FsdGVkX1+vlfFH8Q9XdZ9t9h2bABGYAZltEYAX6UM=',
        ]
    ],
    // 推送服务配置
    'push-server' => [
        // redis通道
        'redis_channel' => 'default',
        'heartbeat'     => 60,
        // 验证app_key
        'apps_query'    => function (?string $appKey, ?string $appId = null): array
        {
            $apps = config('plugin.workbunny.webman-push-server.app.apps', []);
            if($appId !== null){
                foreach ($apps as $app){
                    if($app['app_id'] === $appId){
                        return $app;
                    }
                }
                return [];
            }
            return $apps[$appKey] ?? [];
        },
    ],
    // hook消费者配置
    'hook-server' => [
        // redis通道
        'redis_channel'  => 'default',
        // 队列名
        'queue_key'      => 'workbunny:webman-push-server:webhook-stream',
        // 消费数
        'prefetch_count' => 5,
        // 队列长度
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