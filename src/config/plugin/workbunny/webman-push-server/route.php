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
use Workbunny\WebmanPushServer\ApiRoute;
use Workbunny\WebmanPushServer\PushServer;
use const Workbunny\WebmanPushServer\CHANNEL_TYPE_PRESENCE;
use const Workbunny\WebmanPushServer\CHANNEL_TYPE_PRIVATE;
use function Workbunny\WebmanPushServer\response;

// 注册基础API
\Workbunny\WebmanPushServer\ApiServer::registerApiRoutes();

/**
 * TODO 该接口是样例接口，请自行实现业务
 * 通道鉴权接口
 * @url POST /subscribe/auth
 */
ApiRoute::post('/subscribe/auth', function (Request $request) {
    if(!$channelName = $request->post('channel_name')){
        return response(400, ['error' => 'Required channel_name']);
    }
    if(
        PushServer::getChannelType($channelName) !== CHANNEL_TYPE_PRESENCE and
        PushServer::getChannelType($channelName) !== CHANNEL_TYPE_PRIVATE
    ){
        return response(400, ['error' => 'Invalid channel_name']);
    }
    if(!$socketId = $request->post('socket_id')){
        return response(400, ['error' => 'Required socket_id']);
    }
    $channelData = @json_decode($request->post('channel_data'), true);

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
        PushServer::getChannelType($channelName) === CHANNEL_TYPE_PRESENCE ? $response['channel_data'] : []
    );
    /**
     * @private {"auth": "workbunny:xxxxxxxxxxxxxxxx"}
     * @presence {"auth": "workbunny:xxxxxxxxxxxxxxxx", "channel_data": "{\'name\':\'John\',\'sex\':\'man\'}"}
     */
    return response(200, $response);
});