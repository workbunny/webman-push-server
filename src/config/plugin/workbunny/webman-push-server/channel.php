<?php declare(strict_types=1);
/**
 * @author workbunny/Chaz6chez
 * @email chaz6chez1993@outlook.com
 */

use Workbunny\WebmanPushServer\Channels\RedisChannel;

return [
    'default' => [
        'handler' => RedisChannel::class,
        'channel' => 'server-channel',
    ],
];
