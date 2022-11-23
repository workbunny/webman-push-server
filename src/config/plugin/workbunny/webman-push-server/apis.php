<?php
declare(strict_types=1);

use Pusher\Pusher;
use support\Request;
use support\Response;
use Workbunny\WebmanPushServer\ApiService;
use Workbunny\WebmanPushServer\Server;
use const Workbunny\WebmanPushServer\CHANNEL_TYPE_PRESENCE;

/**
 * 推送js客户端文件
 */
ApiService::get('/plugin/workbunny/webman-push-server/push.js', function (Request $request) {
    return response()->file(base_path().'/vendor/workbunny/webman-push-server/push.js');
});


ApiService::addGroup('/apps/{appId}', function () {
    /** /apps/[app_id]/channels */
    ApiService::get('/channels', function (Server $server, Request $request, array $urlParams): Response {
        // info todo
        $requestInfo = explode(',', $request->get('info', ''));
        if (!isset($explode[3])) {
            $channels = [];
            $prefix = $request->get('filter_by_prefix');
            $returnSubscriptionCount = in_array('subscription_count', $requestInfo);
            try {
                $keys = Server::getStorage()->keys($server->_getChannelStorageKey($appKey));
                foreach ($keys as $key) {
                    $channel = $server->_getChannelName($key);
                    if ($prefix !== null) {
                        if (strpos($channel, $prefix) !== 0) {
                            continue;
                        }
                    }
                    $channels[$channel] = [];
                    if ($returnSubscriptionCount) {
                        $channels[$channel]['subscription_count'] = $server->getStorage()->hGet($key, 'subscription_count');
                    }
                }
                return new Response(200, [], json_encode(['channels' => $channels], JSON_UNESCAPED_UNICODE));
            }catch (\Throwable $throwable){
                return new Response(500, [], 'Server Error [Channels]');
            }
        }
        $channel = $explode[3];
        // users
        if (isset($explode[4])) {
            if ($explode[4] !== 'users') {
                return new Response(400, [], 'Bad Request');
            }
            $userIdArray = [];
            try {
                $keys = $server->getStorage()->keys($server->_getUserStorageKey($appKey, $channel));
                $userCount = count($keys);
                foreach ($keys as $key){
                    $userIdArray['id'] = $server->_getUserId($key);
                }
                return new Response(200, [], json_encode($userIdArray, JSON_UNESCAPED_UNICODE));
                $subscriptionCount = 0;
                if($channelInfo['occupied'] = $server->getStorage()->exists($server->_getChannelStorageKey($appKey, $channel))){
                    $subscriptionCount = $server->getStorage()->hGet($server->_getChannelStorageKey($appKey, $channel), 'subscription_count');
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
        return new Response();
    });

    /** /apps/[app_id]/channels/[channel_name] */
    ApiService::get('/channels/{channelName}', function (Server $server, Request $request, array $urlParams): Response {
// todo
        $channelName = $urlParams['channelName'];
        dump($request->path());
        return new Response();
    });

    /**
     * 发布事件
     * @url /apps/[app_id]/events
     * @method POST
     */
    ApiService::post('/events', function (Server $server, Request $request, array $urlParams): Response {
        $appKey = $request->get('auth_key');
        if($channels = $request->post('channels') or !is_array($channels)){
            return \Workbunny\WebmanPushServer\response(400, ['error' => 'Required channels']);
        }
        if($event = $request->post('name')){
            return \Workbunny\WebmanPushServer\response(400, ['error' => 'Required name']);
        }
        if($data = $request->post('data')){
            return \Workbunny\WebmanPushServer\response(400, ['error' => 'Required data']);
        }
        foreach ($channels as $channel) {
            $socket_id = $package['socket_id'] ?? null;
            $server->publishToClients($appKey, $channel, $event, $data, $socket_id);
        }
        return \Workbunny\WebmanPushServer\response(200, '{}');
    });

    /**
     * 批量发布
     * @url /apps/[app_id]/batch_events
     * @method POST
     */
    ApiService::post('/batch_events', function (Server $server, Request $request, array $urlParams): Response {
        $appKey = $request->get('auth_key');
        $packages = $request->post('batch');
        if (!$packages) {
            return \Workbunny\WebmanPushServer\response(400,['error' => 'Required batch']);
        }
        foreach ($packages as $package) {
            $channel = $package['channel'];
            $event = $package['name'];
            $data = $package['data'];
            $socket_id = $package['socket_id'] ?? null;
            $server->publishToClients($appKey, $channel, $event, $data, $socket_id);
        }
        return \Workbunny\WebmanPushServer\response(200,'{}');
    });

    /** /apps/[app_id]/users/[user_id]/terminate_connections */
    ApiService::post('/users/{userId}/terminate_connections', function (Server $server, Request $request, array $urlParams): Response {
        dump($request->path()); // todo
        return new Response();
    });

    /**
     * 获取通道 所有userId
     * @url /apps/[app_id]/channels/[channel_name]/users
     * @method GET
     */
    ApiService::get('/channels/{channelName}/users', function (Server $server, Request $request, array $urlParams): Response {
        $appKey = $request->get('auth_key');
        $channelName = $urlParams['channelName'];
        $userIdArray = [];
        try {
            $channelType = Server::getStorage()->hGet($server->_getChannelStorageKey($appKey, $channelName), 'type');
            if(!$channelType){
                return \Workbunny\WebmanPushServer\response(404, ['error' => "Not Found [$channelName]"]);
            }
            if($channelType !== CHANNEL_TYPE_PRESENCE) {
                return \Workbunny\WebmanPushServer\response(400, ['error' => "Invalid channel [$channelName]"]);
            }
            $userKeys = Server::getStorage()->keys($server->_getUserStorageKey($appKey, $channelName));
            foreach ($userKeys as $userKey) {
                $userIdArray[] = Server::getStorage()->hGet($userKey,'user_id');
            }
            return \Workbunny\WebmanPushServer\response(200, ['users' => $userIdArray]);
        }catch (\Throwable $throwable){
            //TODO log
            return \Workbunny\WebmanPushServer\response(500,'Server Error [users]');
        }
    });

}, function (Closure $next, Server $server, Request $request, array $urlParams): Response {
    if($appId = $urlParams['appId'] ?? null){
        if (!($appKey = $request->get('auth_key'))) {
            return \Workbunny\WebmanPushServer\response(400,['error' => 'Required auth_key']);
        }
        $apps = config_path('plugin.workbunny.webman-push-server.app.push-server.apps_query')($appKey, $appId);
        if(!$apps){
            return \Workbunny\WebmanPushServer\response(401,['error' => 'Invalid auth_key']);
        }
        $params = $request->get();
        unset($params['auth_signature']);
        $realAuthSignature = Pusher::build_auth_query_params($appKey, $apps['app_secret'], $request->method(), $request->path(), $params)['auth_signature'];
        if ($request->get('auth_signature') !== $realAuthSignature) {
            return \Workbunny\WebmanPushServer\response(401,['error' => 'Invalid signature']);
        }
    }
    return $next($next, $server, $request, $urlParams);
});











