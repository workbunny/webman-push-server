<?php
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
        $pushServer->send($connection, null, EVENT_PONG, '{}');
    }
}