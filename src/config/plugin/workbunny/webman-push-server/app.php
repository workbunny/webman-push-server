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

return [
    'enable'      => true,
    // 验证app_key
    'app_verify' => function (string $appKey): array
    {
        // todo 这里是模拟的app配置，实际过程中这里可以是通过数据库读取
        // $apps = Db::select('apps')->toArray();
        $apps = [
            'workbunny' => [
                'app_id'     => 1,
                'app_key'    => 'workbunny',
                'app_secret' => 'U2FsdGVkX1+vlfFH8Q9XdZ9t9h2bABGYAZltEYAX6UM=',
            ]
        ];
        return $apps[$appKey] ?? [];
    },
    // 推送服务配置
    'push-server' => [
        'port'      => 8001,
        // 心跳检查，0为不检查
        'heartbeat' => 60,
        // 流量统计间隔
        'traffic_statistics_interval' => 0,
    ],
    // api服务配置
    'api-server' => [
        'port'                          => 8002,
        // 流量统计间隔
        'traffic_statistics_interval'   => 0,
    ],
];