<?php
declare(strict_types=1);

namespace Workbunny\WebmanPushServer\Apis;

use Workbunny\WebmanPushServer\Server;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Response;

class Events extends AbstractApis
{
    /** @inheritDoc */
    public function response(string $appKey, Server $pushServer, TcpConnection $connection, $data): void
    {
        $package = json_decode($data->rawBody(), true);
        if (!$package) {
            $connection->send(new Response(401, [], 'Invalid signature'));
            return;
        }
        $channels = $package['channels'];
        $event = $package['name'];
        $data = $package['data'];
        foreach ($channels as $channel) {
            $socket_id = $package['socket_id'] ?? null;
            $pushServer->publishToClients($appKey, $channel, $event, $data, $socket_id);
        }
        $connection->send(new Response(200, [], '{}'));
    }
}