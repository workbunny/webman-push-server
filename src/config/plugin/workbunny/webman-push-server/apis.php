<?php
declare(strict_types=1);

use Pusher\Pusher;
use support\Request;
use support\Response;
use Workbunny\WebmanPushServer\ApiRoute;
use Workbunny\WebmanPushServer\Server;
use const Workbunny\WebmanPushServer\CHANNEL_TYPE_PRESENCE;

/**
 * 推送js客户端文件
 */
ApiRoute::get('/plugin/workbunny/webman-push-server/push.js', function (Request $request) {
    return response()->file(base_path().'/vendor/workbunny/webman-push-server/push.js');
});


ApiRoute::addGroup('/apps/{appId}', function () {

    /**
     * 获取所有channel
     * @url /apps/[app_id]/channels
     * @method GET
     */
    ApiRoute::get('/channels', function (Server $server, Request $request, array $urlParams): Response {
        $appKey = $request->get('auth_key');
        $requestInfo = explode(',', $request->get('info', ''));
        $prefix = $request->get('filter_by_prefix');
        $returnSubscriptionCount = in_array('subscription_count', $requestInfo);
        $channels = [];
        $fields = ['type'];
        if(in_array('subscription_count', $requestInfo)){
            $fields[] = 'subscription_count';
        }
        if(in_array('user_count', $requestInfo)){
            $fields[] = 'user_count';
        }
        try {
            $keys = Server::getStorage()->keys($server->_getChannelStorageKey($appKey));
            foreach ($keys as $key) {
                $channel = $server->_getChannelName($key);
                $channelType = $server->_getChannelType($channel);
                if($prefix !== null and $channelType !== $prefix){
                    continue;
                }
                ;
                $channels[$channel] = Server::getStorage()->hMGet($key, $fields) ?? [];
            }
            return \Workbunny\WebmanPushServer\response(200, ['channels' => $channels]);
        }catch (\Throwable $throwable){
            //TODO log
            return \Workbunny\WebmanPushServer\response(500, 'Server Error [Channels]');
        }
    });

    /**
     * 获取通道信息
     * @url /apps/[app_id]/channels/[channel_name]
     * @method GET
     */
    ApiRoute::get('/channels/{channelName}', function (Server $server, Request $request, array $urlParams): Response {
        $appKey = $request->get('auth_key');
        $requestInfo = explode(',', $request->get('info', ''));
        $channelName = $urlParams['channelName'];
        $fields = ['type'];
        if(in_array('subscription_count', $requestInfo)){
            $fields[] = 'subscription_count';
        }
        if(in_array('user_count', $requestInfo)){
            $fields[] = 'user_count';
        }
        try {
            $channels = Server::getStorage()->hMGet($server->_getChannelStorageKey($appKey,$channelName), $fields);
            return \Workbunny\WebmanPushServer\response(200, $channels ? array_merge([
                'occupied' => true,
            ], $channels) : '{}');
        }catch (RedisException $exception){
            //TODO log
            return \Workbunny\WebmanPushServer\response(500,'Server Error [channel]');
        }
    });

    /**
     * 发布事件
     * @url /apps/[app_id]/events
     * @method POST
     */
    ApiRoute::post('/events', function (Server $server, Request $request, array $urlParams): Response {
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
    ApiRoute::post('/batch_events', function (Server $server, Request $request, array $urlParams): Response {
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

    /**
     * 终止用户所有连接
     * @url /apps/[app_id]/users/[user_id]/terminate_connections
     * @method POST
     */
    ApiRoute::post('/users/{userId}/terminate_connections', function (Server $server, Request $request, array $urlParams): Response {
        $appKey = $request->get('auth_key');
        $userId = $urlParams['userId'];
        $socketIds = [];
        $userKeys = Server::getStorage()->keys($server->_getUserStorageKey($appKey, null, $userId));
        foreach ($userKeys as $userKey){
            $socketIds[] = Server::getStorage()->hGet($userKey, 'socket_id');
        }
        foreach ($socketIds as $socketId){
            $server->terminateConnections($appKey, $socketId, [
                'message' => 'Terminate connection by API'
            ]);
        }
        return \Workbunny\WebmanPushServer\response(200, '{}');
    });

    /**
     * 获取通道 所有userId
     * @url /apps/[app_id]/channels/[channel_name]/users
     * @method GET
     */
    ApiRoute::get('/channels/{channelName}/users', function (Server $server, Request $request, array $urlParams): Response {
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











