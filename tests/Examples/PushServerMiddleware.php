<?php declare(strict_types=1);
/**
 * @author workbunny/Chaz6chez
 * @email chaz6chez1993@outlook.com
 */

namespace Tests\Examples;

use Workbunny\WebmanPushServer\Traits\ChannelMethods;

class PushServerMiddleware
{
    use ChannelMethods;


    /** @inheritDoc */
    public static function _subscribeResponse(string $type, array $data): void
    {
        // TODO
    }
}