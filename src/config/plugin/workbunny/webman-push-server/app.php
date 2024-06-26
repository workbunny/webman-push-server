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
    // 心跳检查， 0为不检查
    'heartbeat'   => 60,
    // 推送服务配置
    'push-server' => [
        // 心跳检查，0为不检查
        'heartbeat' => 60,
    ],
    // api服务配置
    'api-server' => [
        // 验证app_key
        'apps_query' => function (string $appKey, ?string $appId = null): array
        {
            // 默认app_key
            $apps = [
                'workbunny' => [
                    'app_id'     => '1',
                    'app_key'    => 'workbunny',
                    'app_secret' => 'U2FsdGVkX1+vlfFH8Q9XdZ9t9h2bABGYAZltEYAX6UM=',
                ]
            ];
            $app = $apps[$appKey] ?? [];
            if ($appId === null){
                return $app;
            }
            return (($app['app_id'] ?? null) === $appId) ? $app : [];
        },
    ],
];