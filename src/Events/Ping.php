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

namespace Workbunny\WebmanPushServer\Events;

use Workbunny\WebmanPushServer\Server;
use Workerman\Connection\TcpConnection;
use const Workbunny\WebmanPushServer\EVENT_PONG;

class Ping extends AbstractEvent
{
    /**
     * @inheritDoc
     */
    public function response(Server $pushServer, TcpConnection $connection, array $request): void
    {
        /**
         * {"event":"pusher:pong","data":"{}"}
         */
        $pushServer->send($connection, null, EVENT_PONG, new \stdClass());
    }
}