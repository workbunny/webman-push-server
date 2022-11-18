<?php
declare(strict_types=1);

namespace Workbunny\WebmanPushServer\Events;

use Workbunny\WebmanPushServer\HookServer;
use Workbunny\WebmanPushServer\Server;
use Workerman\Connection\TcpConnection;
use function Workbunny\WebmanPushServer\uuid;
use const Workbunny\WebmanPushServer\PUSH_SERVER_EVENT_CLIENT_EVENT;

class ServerEvent extends AbstractEvent
{
    public function response(Server $pushServer, TcpConnection $connection, array $request): void
    {
        try {
            HookServer::publish(PUSH_SERVER_EVENT_CLIENT_EVENT, array_merge($request, [
                'id'      => uuid(),
                'app_key' => $pushServer->_getConnectionProperty($connection,'appKey', 'unknown')
            ]));
        }catch (\RedisException $exception){
            error_log($exception->getMessage() . PHP_EOL);
        }
    }
}