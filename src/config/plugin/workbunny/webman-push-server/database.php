<?php declare(strict_types=1);

return [
    'connections' => [
        'local-storage' => [
            'driver'   => 'sqlite',
            'database' => runtime_path() . '/workbunny/webman-push-server/temp.db',
            'prefix'   => '',
        ],
    ]
];
