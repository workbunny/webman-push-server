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

use Workbunny\WebmanPushServer\ApiClient;
use Workerman\Protocols\Http\Request;
use support\Response;
use Workbunny\WebmanPushServer\ApiRoute;
use Workbunny\WebmanPushServer\Server;
use const Workbunny\WebmanPushServer\CHANNEL_TYPE_PRESENCE;
use const Workbunny\WebmanPushServer\CHANNEL_TYPE_PRIVATE;
use function Workbunny\WebmanPushServer\response;

/**
 * @url GET /index
 */
ApiRoute::get('/index', function () {
    return response(200, 'Hello Workbunny!');
});

/**
 * @url GET /plugin/workbunny/webman-push-server/push.js
 */
ApiRoute::get('/plugin/workbunny/webman-push-server/push.js', function () {
    return response(200, '')->file(base_path().'/vendor/workbunny/webman-push-server/push.js');
});

/**
 * TODO 该接口是样例接口，请自行实现业务
 * 订阅状态频道鉴权接口
 * @url POST /subscribe/presence/auth
 */
ApiRoute::post('/subscribe/presence/auth', function (Server $server, Request $request) {
    if(!$channelName = $request->post('channel_name')){
        return response(400, ['error' => 'Required channel_name']);
    }
    if($server->_getChannelType($channelName) !== CHANNEL_TYPE_PRESENCE){
        return response(400, ['error' => 'Invalid channel_name']);
    }
    if(!$socketId = $request->post('socket_id')){
        return response(400, ['error' => 'Required socket_id']);
    }
    $channelData = $request->post('channel_data');

    /**
     * TODO 通道是否可以进行监听取决与业务是否对用户进行授权，常规实现方式是通过用户信息与 channel 进行绑定授权，自行实现
     */

    /**
     * TODO channel_data 信息获取实现方式推荐如下
     * TODO 1. 前端通过查询接口获取 channel_data 相关信息，再将数据传入该接口，随后与 session 进行校验等
     * TODO 2. 通过 socketId 等信息查询数据库获取 channel_data 相关信息
     * TODO 3. 通过 session 比对信息，获取 channel_data 相关信息
     * TODO 以上方式任选一种适合自己的实现，以下为模拟操作
     */
    $response['channel_data'] = $channelData ?? [
        'user_id' => '100',
        'user_info' => "{\'name\':\'John\',\'sex\':\'man\'}"
    ];

    // 获取加密sign
    $response['auth'] = ApiClient::subscribeAuth(
        'workbunny', // TODO 动态配置
        'U2FsdGVkX1+vlfFH8Q9XdZ9t9h2bABGYAZltEYAX6UM=', // TODO 动态配置
        $socketId,
        $channelName,
        $response['channel_data']
    );
    // 返回格式 {"auth": "workbunny:xxxxxxxxxxxxxxxx, "channel_data": "{\'name\':\'John\',\'sex\':\'man\'}"};
    return response(200, $response);
});

/**
 * TODO 该接口是样例接口，请自行实现业务
 * 订阅私有频道鉴权接口
 * @url POST /subscribe/private/auth
 */
ApiRoute::post('/subscribe/private/auth', function (Server $server, Request $request) {
    if(!$channelName = $request->post('channel_name')){
        return response(400, ['error' => 'Required channel_name']);
    }
    if($server->_getChannelType($channelName) !== CHANNEL_TYPE_PRIVATE){
        return response(400, ['error' => 'Invalid channel_name']);
    }
    if(!$socketId = $request->post('socket_id')){
        return response(400, ['error' => 'Required socket_id']);
    }

    /**
     * TODO 通道是否可以进行监听取决与业务是否对用户进行授权，常规实现方式是通过用户信息与 channel 进行绑定授权，请根据业务自行实现
     */

    // 获取加密sign
    $response['auth'] = ApiClient::subscribeAuth(
        'workbunny', // TODO 动态配置
        'U2FsdGVkX1+vlfFH8Q9XdZ9t9h2bABGYAZltEYAX6UM=', // TODO 动态配置
        $socketId,
        $channelName
    );
    // 返回格式 {"auth": "workbunny:xxxxxxxxxxxxxxxx"}"};
    return response(200, $response);
});

/**
 * API路由
 */
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
                $channels[$channel] = Server::getStorage()->hMGet($key, $fields) ?? [];
            }
            return response(200, ['channels' => $channels]);
        }catch (\Throwable $throwable){
            //TODO log
            return response(500, 'Server Error [Channels]');
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
            return response(200, $channels ? array_merge([
                'occupied' => true,
            ], $channels) : '{}');
        }catch (RedisException $exception){
            //TODO log
            return response(500,'Server Error [channel]');
        }
    });

    /**
     * 发布事件
     * @url /apps/[app_id]/events
     * @method POST
     */
    ApiRoute::post('/events', function (Server $server, Request $request, array $urlParams): Response {
        $appKey = $request->get('auth_key');
        $channel = $request->post('channel');
        $channels = $request->post('channels', []);
//        if($channels = $request->post('channels') or !is_array($channels)){
//            return response(400, ['error' => 'Required channels']);
//        }
        if($event = $request->post('name')){
            return response(400, ['error' => 'Required name']);
        }
        if($data = $request->post('data')){
            return response(400, ['error' => 'Required data']);
        }
        $channels = ($channel !== null) ? [(string)$channel] : $channels;
        foreach ($channels as $channel) {
            $socket_id = $package['socket_id'] ?? null;
            $server->publishToClients($appKey, $channel, $event, $data, $socket_id);
        }
        return response(200, '{}');
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
            return response(400,['error' => 'Required batch']);
        }
        foreach ($packages as $package) {
            $channel = $package['channel'];
            $event = $package['name'];
            $data = $package['data'];
            $socket_id = $package['socket_id'] ?? null;
            $server->publishToClients($appKey, $channel, $event, $data, $socket_id);
        }
        return response(200,'{}');
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
        return response(200, '{}');
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
                return response(404, ['error' => "Not Found [$channelName]"]);
            }
            if($channelType !== CHANNEL_TYPE_PRESENCE) {
                return response(400, ['error' => "Invalid channel [$channelName]"]);
            }
            $userKeys = Server::getStorage()->keys($server->_getUserStorageKey($appKey, $channelName));
            foreach ($userKeys as $userKey) {
                $userIdArray[] = Server::getStorage()->hGet($userKey,'user_id');
            }
            return response(200, ['users' => $userIdArray]);
        }catch (\Throwable $throwable){
            //TODO log
            return response(500,'Server Error [users]');
        }
    });

}, function (Closure $next, Server $server, Request $request, array $urlParams): Response {
    if($appId = $urlParams['appId'] ?? null){
        if (!($appKey = $request->get('auth_key'))) {
            return response(400,['error' => 'Required auth_key']);
        }
        $apps = Server::getConfig('apps_query')($appKey, $appId);
        if(!$apps){
            return response(401,['error' => 'Invalid auth_key']);
        }
        $params = $request->get();
        unset($params['auth_signature']);
        $realAuthSignature = ApiClient::routeAuth($appKey, $apps['app_secret'], $request->method(), $request->path(), $params);
        if ($request->get('auth_signature') !== $realAuthSignature) {
            return response(401,['error' => 'Invalid signature']);
        }
    }
    return $next($server, $request, $urlParams);
});