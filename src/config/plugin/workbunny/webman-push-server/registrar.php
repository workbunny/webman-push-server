<?php declare(strict_types=1);
/**
 * @author workbunny/Chaz6chez
 * @email chaz6chez1993@outlook.com
 */

use Workbunny\WebmanPushServer\Registrars\RedisRegistrar;

return [
    'handler'        => new RedisRegistrar(),
    'timer_interval' => 60
];