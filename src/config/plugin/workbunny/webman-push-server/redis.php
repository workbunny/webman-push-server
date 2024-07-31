<?php declare(strict_types=1);

return [
    // push server 储存器
    'server-storage' => [
        'host'     => '172.17.0.1',
        'password' => '',
        'port'     => 6379,
        'database' => 0,
    ],
    // 服务通讯频道
    'server-channel' => [
        'host'     => '172.17.0.1',
        'password' => '',
        'port'     => 6379,
        'database' => 0,
        'options'  => []
    ],
    // redis注册器配置
    'server-registrar' => [
        'host'     => '172.17.0.1',
        'password' => '',
        'port'     => 6379,
        'database' => 0,
        'options'  => []
    ]
];
