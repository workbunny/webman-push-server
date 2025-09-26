<?php declare(strict_types=1);

namespace Workbunny\WebmanPushServer\Traits;

use Closure;
use RedisException;
use support\Log;
use Workbunny\WebmanPushServer\ApiClient;
use Workbunny\WebmanPushServer\ApiRoute;
use Workbunny\WebmanPushServer\ApiServer;
use Workbunny\WebmanPushServer\PublishTypes\AbstractPublishType;
use Workbunny\WebmanPushServer\PushServer;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use function Workbunny\WebmanPushServer\ms_timestamp;
use function Workbunny\WebmanPushServer\response;
use const Workbunny\WebmanPushServer\CHANNEL_TYPE_PRESENCE;
use const Workbunny\WebmanPushServer\EVENT_TERMINATE_CONNECTION;

trait BaseApiRoutes
{

    /**
     * 注册基础API路由
     * @return void
     */
    public static function registerApiRoutes(): void
    {
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
         * API路由
         */
        ApiRoute::addGroup('/apps/{appId}', function () {

            /**
             * 获取所有channel
             * @url /apps/[app_id]/channels
             * @method GET
             */
            ApiRoute::get('/channels', function (Request $request, array $urlParams): Response {
                $appKey = $request->get('auth_key');
                $requestInfo = explode(',', $request->get('info', ''));
                $prefix = $request->get('filter_by_prefix');
                $channels = [];
                $fields = ['type'];
                if (in_array('subscription_count', $requestInfo)){
                    $fields[] = 'subscription_count';
                }
                if (in_array('user_count', $requestInfo)){
                    $fields[] = 'user_count';
                }
                try {
                    $storage = PushServer::getStorageClient();
                    $keys = $storage->keys(PushServer::getChannelStorageKey($appKey));
                    foreach ($keys as $key) {
                        $channel = PushServer::getChannelName($key);
                        $channelType = PushServer::getChannelType($channel);
                        if($prefix !== null and $channelType !== $prefix){
                            continue;

                        }
                        $channels[$channel] = $storage->hMGet($key, $fields) ?? [];
                    }
                    return response(200, ['channels' => $channels]);
                } catch (\Throwable $exception) {
                    Log::channel('plugin.workbunny.webman-push-server.warning')
                        ->warning("[API-SERVER] {$exception->getMessage()}", [
                            'method' => __METHOD__
                        ]);
                    return response(500, 'Server Error [Channels]');
                }
            });

            /**
             * 获取通道信息
             * @url /apps/[app_id]/channels/[channel_name]
             * @method GET
             */
            ApiRoute::get('/channels/{channelName}', function (Request $request, array $urlParams): Response {
                $appKey = $request->get('auth_key');
                $requestInfo = explode(',', $request->get('info', ''));
                $channelName = $urlParams['channelName'];
                $fields = ['type'];
                if (in_array('subscription_count', $requestInfo)){
                    $fields[] = 'subscription_count';
                }
                if (in_array('user_count', $requestInfo)){
                    $fields[] = 'user_count';
                }
                try {
                    $storage = PushServer::getStorageClient();
                    $channels = $storage->hMGet(PushServer::getChannelStorageKey($appKey,$channelName), $fields);
                    return response(200, $channels ? array_merge([
                        'occupied' => true,
                    ], $channels) : '{}');
                } catch (RedisException $exception){
                    Log::channel('plugin.workbunny.webman-push-server.warning')
                        ->warning("[API-SERVER] {$exception->getMessage()}", [
                            'method' => __METHOD__
                        ]);
                    return response(500,'Server Error [channel]');
                }
            });

            /**
             * 发布事件
             * @url /apps/[app_id]/events
             * @method POST
             */
            ApiRoute::post('/events', function (Request $request, array $urlParams): Response {
                $appKey = $request->get('auth_key');
                $channel = $request->post('channel');
                $channels = $request->post('channels', []);
                $socketId = $request->post('socket_id');
//        if($channels = $request->post('channels') or !is_array($channels)){
//            return response(400, ['error' => 'Required channels']);
//        }
                if(!$event = $request->post('name')){
                    return response(400, ['error' => 'Required name']);
                }
                if(!$data = $request->post('data')){
                    return response(400, ['error' => 'Required data']);
                }
                $channels = ($channel !== null) ? [(string)$channel] : $channels;
                foreach ($channels as $channel) {
                    PushServer::publishUseRetry(AbstractPublishType::PUBLISH_TYPE_CLIENT, PushServer::filter([
                        'appKey'    => $appKey,
                        'socketId'  => $socketId,
                        'timestamp' => ms_timestamp(),
                        'channel'   => $channel,
                        'event'     => $event,
                        'data'      => $data,
                    ]));
                }
                return response(200, json_encode([
                    'channels' => $channels
                ], JSON_UNESCAPED_UNICODE));
            });

            /**
             * 批量发布
             * @url /apps/[app_id]/batch_events
             * @method POST
             */
            ApiRoute::post('/batch_events', function (Request $request, array $urlParams): Response {
                $appKey = $request->get('auth_key');
                $packages = $request->post('batch');
                if (!$packages) {
                    return response(400,['error' => 'Required batch']);
                }
                $channels = [];
                foreach ($packages as $package) {
                    $channels[] = $channel = $package['channel'];
                    $event = $package['name'];
                    $data = $package['data'];
                    $socketId = $package['socket_id'] ?? null;
                    PushServer::publishUseRetry(AbstractPublishType::PUBLISH_TYPE_CLIENT, PushServer::filter([
                        'appKey'    => $appKey,
                        'socketId'  => $socketId,
                        'timestamp' => ms_timestamp(),
                        'channel'   => $channel,
                        'event'     => $event,
                        'data'      => $data,
                    ]));
                }
                return response(200,json_encode([
                    'channels' => $channels
                ], JSON_UNESCAPED_UNICODE));
            });

            /**
             * 终止用户所有连接
             * @url /apps/[app_id]/users/[user_id]/terminate_connections
             * @method POST
             */
            ApiRoute::post('/users/{userId}/terminate_connections', function (Request $request, array $urlParams): Response {
                $appKey = $request->get('auth_key');
                $userId = $urlParams['userId'];
                $socketIds = [];
                $storage = PushServer::getStorageClient();
                $userKeys = $storage->keys(PushServer::getUserStorageKey($appKey, null, $userId));
                foreach ($userKeys as $userKey){
                    $socketIds[] = $storage->hGet($userKey, 'socket_id');
                }
                foreach ($socketIds as $socketId){
                    PushServer::publishUseRetry(AbstractPublishType::PUBLISH_TYPE_SERVER, [
                        'appKey'    => $appKey,
                        'socket_id' => $socketId,
                        'timestamp' => ms_timestamp(),
                        'event'     => EVENT_TERMINATE_CONNECTION,
                        'data'      => [
                            'type'      => 'API',
                            'message'   => 'Terminate connection by API'
                        ]
                    ]);
                }
                return response(200, '{}');
            });

            /**
             * 获取通道 所有userId
             * @url /apps/[app_id]/channels/[channel_name]/users
             * @method GET
             */
            ApiRoute::get('/channels/{channelName}/users', function (Request $request, array $urlParams): Response {
                $appKey = $request->get('auth_key');
                $channelName = $urlParams['channelName'];
                $userIdArray = [];
                try {
                    $storage = PushServer::getStorageClient();
                    $channelType = $storage->hGet(PushServer::getChannelStorageKey($appKey, $channelName), 'type');
                    if(!$channelType){
                        return response(404, ['error' => "Not Found [$channelName]"]);
                    }
                    if($channelType !== CHANNEL_TYPE_PRESENCE) {
                        return response(400, ['error' => "Invalid channel [$channelName]"]);
                    }
                    $userKeys = $storage->keys(PushServer::getUserStorageKey($appKey, $channelName));
                    foreach ($userKeys as $userKey) {
                        $userIdArray[] = $storage->hGet($userKey,'user_id');
                    }
                    return response(200, ['users' => $userIdArray]);
                } catch (\Throwable $throwable){
                    Log::channel('plugin.workbunny.webman-push-server.warning')
                        ->warning("[API-SERVER] {$throwable->getMessage()}", [
                            'method' => __METHOD__
                        ]);
                    return response(500,'Server Error [users]');
                }
            });

        },
            function (Closure $next, Request $request, array $urlParams, TcpConnection $connection): Response {
            if ($appId = $urlParams['appId'] ?? null) {
                if (!($appKey = $request->get('auth_key'))) {
                    return response(400,['error' => 'Required auth_key']);
                }
                if ($appVerifyCallback = ApiServer::getConfig('app_verify', getBase: true)) {
                    if (
                        !$app = call_user_func($appVerifyCallback, $appKey) or
                        ($app['app_id'] !== intval($appId))
                    ) {
                        return response(401,['error' => 'Invalid auth_key']);
                    }
                    $params = $request->get();
                    unset($params['auth_signature']);
                    $realAuthSignature = ApiClient::routeAuth($appKey, $app['app_secret'], $request->method(), $request->path(), $params);
                    if ($request->get('auth_signature') !== $realAuthSignature) {
                        return response(401,['error' => 'Invalid signature']);
                    }
                }
            }
            return $next($request, $urlParams, $connection);
        });
    }
}