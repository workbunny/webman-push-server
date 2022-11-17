<?php
declare(strict_types=1);

namespace Workbunny\WebmanPushServer\Services\Apis;

use Workbunny\WebmanPushServer\Server;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Response;

class BatchEvents extends AbstractApis
{
    /**
     * @param string $appKey
     * @param Server $pushServer
     * @param TcpConnection $connection
     * @param $data
     * @return void
     */
    public function response(string $appKey, Server $pushServer, TcpConnection $connection, $data): void
    {
        $packages = json_decode($data->rawBody(), true);
        if (!$packages || !isset($packages['batch'])) {
            $connection->send(new Response(400, [], 'Bad request'));
            return;
        }
        $packages = $packages['batch'];
        foreach ($packages as $package) {
            $channel = $package['channel'];
            $event = $package['name'];
            $data = $package['data'];
            $socket_id = $package['socket_id'] ?? null;
            $pushServer->publishToClients($appKey, $channel, $event, $data, $socket_id);
        }
        $connection->send('{}');
    }
}