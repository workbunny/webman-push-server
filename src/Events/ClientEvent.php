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

namespace Workbunny\WebmanPushServer\Events;

use Workbunny\WebmanPushServer\HookServer;
use Workbunny\WebmanPushServer\Server;
use Workerman\Connection\TcpConnection;
use function Workbunny\WebmanPushServer\uuid;
use const Workbunny\WebmanPushServer\CHANNEL_TYPE_PRESENCE;
use const Workbunny\WebmanPushServer\CHANNEL_TYPE_PRIVATE;
use const Workbunny\WebmanPushServer\PUSH_SERVER_EVENT_CLIENT_EVENT;

class ClientEvent extends AbstractEvent
{
    /**
     * 客户端仅能向private和presence通道发送消息
     * public用于服务广播通知
     * @param Server $pushServer
     * @param TcpConnection $connection
     * @param array $request
     * @return void
     */
    public function response(Server $pushServer, TcpConnection $connection, array $request): void
    {
        if(!$channel = $request['channel'] ?? null){
            $pushServer->error($connection, null, 'Bad channel');
            return;
        }
        if(!$data = $request['data'] ?? []){
            $pushServer->error($connection, null, 'Bad data');
            return;
        }
        // 客户端触发事件必须是private 或者 presence的channel
        $channelType = $pushServer->_getChannelType($channel);
        if ($channelType !== CHANNEL_TYPE_PRIVATE and $channelType !== CHANNEL_TYPE_PRESENCE) {
            $pushServer->error($connection, null, 'Client event rejected - only supported on private and presence channels');
            return;
        }
        // 当前链接没有订阅这个channel
        if (!isset($pushServer->_getConnectionProperty($connection, 'channels')[$channel])) {
            $pushServer->error($connection, null, 'Client event rejected - you didn\'t subscribe this channel');
            return;
        }
        // 事件必须以client-为前缀
        if (strpos($this->getEvent(), 'client-') !== 0) {
            $pushServer->error($connection, null, 'Client event rejected - client events must be prefixed by \'client-\'');
            return;
        }

        // @todo 检查是否设置了可前端发布事件
        // 全局发布事件
        $pushServer->publishToClients(
            $appKey = $pushServer->_getConnectionProperty($connection, 'appKey'),
            $channel,
            $this->getEvent(),
            $data,
            $pushServer->_getConnectionProperty($connection,'socketId')
        );
        try {
            HookServer::instance()->publish(PUSH_SERVER_EVENT_CLIENT_EVENT, array_merge($request, [
                'id'      => uuid(),
                'app_key' => $appKey
            ]));
        }catch (\RedisException $exception){
            error_log($exception->getMessage() . PHP_EOL);
        }
    }
}