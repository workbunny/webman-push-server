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

use Workbunny\WebmanPushServer\DefaultHandler;
use Workbunny\WebmanPushServer\WebhookHandler;
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
        // 心跳检查，0为不检查
        'heartbeat'     => 60,
        // channel默认端口
        'channel_port'  => 2206,
        // 验证app_key
        'apps_query'    => function (string $appKey, ?string $appId = null): array
        {
            $apps = config('plugin.workbunny.webman-push-server.app.apps', []);
            $app = $apps[$appKey] ?? [];
            if($appId === null){
                return $app;
            }
            return (($app['app_id'] ?? null) === $appId) ? $app : [];
        },
    ],
    // hook消费者配置
    'hook-server' => [
        // 开关
        'publish_enable'    => true, // false时 所有事件不会被publish
        // redis通道
        'redis_channel'     => 'default',
        // 队列名
        'queue_key'         => 'workbunny:webman-push-server:webhook-stream',
        // pending消息相关
        'pending_timeout'   => 60 * 60, // pending消息过期时间 s
        'claim_interval'    => 5 * 60,  // pending消息处理器定时间隔，0为不进行pending数据的处理 s
        // 消息重入队列定时间隔
        'requeue_interval'  => 5 * 60, // 0为不进行消息重入队列的处理 s
        // 事件消费相关
        'consumer_interval' => 100, // 消费间隔 ms
        'prefetch_count'    => 20, // 每次消费者消费的最大数量
        'queue_limit'       => 0, // 队列长度限制，0为不限制
        // 默认事件处理器
        'hook_handler'      => DefaultHandler::class,
//        // webhook回调事件处理器
//        'hook_handler'      => WebhookHandler::class,
        // webhook相关配置
        'webhook_url'             => 'http://127.0.0.1:8002/webhook', // 样例接口
        'webhook_secret'          => 'YOUR_WEBHOOK_SECRET',           // 样例
        'webhook_request_timeout' => 30,                              // 请求超时时间
        'webhook_connect_timeout' => 30,                              // 连接超时时间
        // 事件列表
        'events'            => [
            PUSH_SERVER_EVENT_MEMBER_ADDED, PUSH_SERVER_EVENT_MEMBER_REMOVED,
            PUSH_SERVER_EVENT_CLIENT_EVENT, PUSH_SERVER_EVENT_SERVER_EVENT,
            PUSH_SERVER_EVENT_CHANNEL_OCCUPIED, PUSH_SERVER_EVENT_CHANNEL_VACATED,
        ],
    ]
];