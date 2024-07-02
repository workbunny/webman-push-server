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

use RedisException;
use support\Log;
use Workbunny\WebmanPushServer\PushServer;
use Workbunny\WebmanPushServer\Traits\ChannelMethods;
use Workerman\Connection\TcpConnection;
use const Workbunny\WebmanPushServer\CHANNEL_TYPE_PRESENCE;
use const Workbunny\WebmanPushServer\CHANNEL_TYPE_PRIVATE;

class ClientEvent extends AbstractEvent
{
    /**
     * 客户端仅能向private和presence通道发送消息
     * public用于服务广播通知
     *
     * @param TcpConnection $connection
     * @param array $request
     * @return void
     */
    public function response(TcpConnection $connection, array $request): void
    {
        // 事件必须以client-为前缀
        if (!str_starts_with($this->getEvent(), 'client-')) {
            PushServer::error($connection, null, 'Client event rejected - client events must be prefixed by \'client-\'');
            return;
        }
        if (!$channel = $request['channel'] ?? null){
            PushServer::error($connection, null, 'Bad channel');
            return;
        }
        if (!$data = $request['data'] ?? []){
            PushServer::error($connection, null, 'Bad data');
            return;
        }
        // 当前链接没有订阅这个channel
        if (!isset(PushServer::getConnectionProperty($connection, 'channels')[$channel])) {
            PushServer::error($connection, null, 'Client event rejected - you didn\'t subscribe this channel');
            return;
        }
        // 客户端触发事件必须是private 或者 presence的channel
        $channelType = PushServer::getChannelType($channel);
        if ($channelType !== CHANNEL_TYPE_PRIVATE and $channelType !== CHANNEL_TYPE_PRESENCE) {
            PushServer::error($connection, null, 'Client event rejected - only supported on private and presence channels');
            return;
        }
        // 广播 客户端消息
        PushServer::publishUseRetry(PushServer::$publishTypeClient, [
            'appKey'    => PushServer::getConnectionProperty($connection,'appKey'),
            'channel'   => $channel,
            'event'     => $this->getEvent(),
            'data'      => $data,
            'socketId'  => PushServer::getConnectionProperty($connection,'socketId')
        ]);
    }
}