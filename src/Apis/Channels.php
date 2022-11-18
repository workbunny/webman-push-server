<?php
declare(strict_types=1);

namespace Workbunny\WebmanPushServer\Apis;

use Workbunny\WebmanPushServer\Server;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Response;

class Channels extends AbstractApis
{
    /** @inheritDoc */
    public function response(string $appKey, Server $pushServer, TcpConnection $connection, $data): void
    {
        // info
        $requestInfo = explode(',', $data->get('info', ''));
        if (!isset($explode[3])) {
            $channels = [];
            $prefix = $data->get('filter_by_prefix');
            $returnSubscriptionCount = in_array('subscription_count', $requestInfo);
            try {
                $keys = $pushServer->getStorage()->keys($pushServer->_getChannelStorageKey($appKey));
                foreach ($keys as $key) {
                    $channel = $pushServer->_getChannelName($key);
                    if ($prefix !== null) {
                        if (strpos($channel, $prefix) !== 0) {
                            continue;
                        }
                    }
                    $channels[$channel] = [];
                    if ($returnSubscriptionCount) {
                        $channels[$channel]['subscription_count'] = $pushServer->getStorage()->hGet($key, 'subscription_count');
                    }
                }
                $connection->send(json_encode(['channels' => $channels], JSON_UNESCAPED_UNICODE));
                return;
            }catch (\Throwable $throwable){
                $connection->send(new Response(500, [], 'Server Error [Channels]'));
                return;
            }
        }
        $channel = $explode[3];
        // users
        if (isset($explode[4])) {
            if ($explode[4] !== 'users') {
                $connection->send(new Response(400, [], 'Bad Request'));
                return;
            }
            $userIdArray = [];
            try {
                $keys = $pushServer->getStorage()->keys($pushServer->_getUserStorageKey($appKey, $channel));
                $userCount = count($keys);
                foreach ($keys as $key){
                    $userIdArray['id'] = $pushServer->_getUserId($key);
                }
                $connection->send(json_encode($userIdArray, JSON_UNESCAPED_UNICODE));
                $subscriptionCount = 0;
                if($channelInfo['occupied'] = $pushServer->getStorage()->exists($pushServer->_getChannelStorageKey($appKey, $channel))){
                    $subscriptionCount = $pushServer->getStorage()->hGet($pushServer->_getChannelStorageKey($appKey, $channel), 'subscription_count');
                }
                foreach ($requestInfo as $name){
                    switch ($name) {
                        case 'user_count':
                            $channelInfo['user_count'] = $userCount;
                            break;
                        case 'subscription_count':
                            $channelInfo['subscription_count'] = $subscriptionCount;
                            break;
                    }
                }
                $connection->send(json_encode($channelInfo, JSON_UNESCAPED_UNICODE));
            }catch (\Throwable $throwable){
                $connection->send(new Response(500, [], 'Server Error [Users]'));
                return;
            }
        }
    }
}