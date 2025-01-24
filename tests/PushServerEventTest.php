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

namespace Tests;

use Tests\MockClass\MockTcpConnection;
use Workbunny\WebmanPushServer\Events\ClientEvent;
use Workbunny\WebmanPushServer\Events\Ping;
use Workbunny\WebmanPushServer\Events\Subscribe;
use Workbunny\WebmanPushServer\Events\Unsubscribe;
use Workbunny\WebmanPushServer\PushServer;
use const Workbunny\WebmanPushServer\EVENT_ERROR;
use const Workbunny\WebmanPushServer\EVENT_PING;
use const Workbunny\WebmanPushServer\EVENT_PONG;
use const Workbunny\WebmanPushServer\EVENT_SUBSCRIBE;
use const Workbunny\WebmanPushServer\EVENT_SUBSCRIPTION_SUCCEEDED;
use const Workbunny\WebmanPushServer\EVENT_UNSUBSCRIBE;
use const Workbunny\WebmanPushServer\EVENT_UNSUBSCRIPTION_SUCCEEDED;


class PushServerEventTest extends BaseTestCase
{

    /**
     * @param array $array
     * @return string
     */
    protected function ArrayToJson(array $array): string
    {
        return json_encode($array, JSON_UNESCAPED_UNICODE);
    }


    public function testPushServerEventPing(){
        // 初始化一个mock tcp连接
        $connection = new MockTcpConnection();
        // 模拟onConnect
        $this->getPushServer()->onConnect($connection);
        // 模拟调用$connection->onWebSocketConnect
        call_user_func(PushServer::getConnectionProperty($connection, 'onWebSocketConnect'), $connection, $this->getWebsocketHeader());
        // 断言检测心跳计数为0
        $this->assertEquals(
            0, PushServer::getConnectionProperty($connection, 'clientNotSendPingCount', 'has-not')
        );
        // 模拟未心跳，累计计数
        PushServer::setConnectionProperty($connection, 'clientNotSendPingCount', 1);
        // 断言检测心跳累计为1
        $this->assertEquals(
            1, PushServer::getConnectionProperty($connection, 'clientNotSendPingCount', 'has-not')
        );
        // 断言检测事件初始为null
        $this->assertNull($this->getPushServer()->getLastEvent());
        // 设置回执buffer null
        $connection->setSendBuffer(null);
        // 断言检测回执buffer为null
        $this->assertNull($connection->getSendBuffer());

        // 模拟发送 ping
        $this->getPushServer()->onMessage($connection, $this->ArrayToJson([
            'event' => EVENT_PING
        ]));
        // 断言检测心跳计数为0
        $this->assertEquals(
            0, PushServer::getConnectionProperty($connection, 'clientNotSendPingCount', 'has-not')
        );
        // 断言检测ping事件
        $this->assertTrue($this->getPushServer()->getLastEvent() instanceof Ping);
        // 断言检测回执pong
        $data = @json_decode($connection->getSendBuffer(), true) ?: [];
        $this->assertEquals(EVENT_PONG, $data['event'] ?? null);
        $this->assertEquals([], $data['data'] ?? null);
    }


    public function testPushServerEventSubscribe()
    {
        $key = __FUNCTION__;
        // 初始化一个mock tcp连接
        $connection = new MockTcpConnection();
        // 模拟onConnect
        $this->getPushServer()->onConnect($connection);
        // 模拟调用$connection->onWebSocketConnect
        call_user_func(PushServer::getConnectionProperty($connection, 'onWebSocketConnect'), $connection, $this->getWebsocketHeader());
        // 断言检测心跳计数为0
        $this->assertEquals(
            0, PushServer::getConnectionProperty($connection, 'clientNotSendPingCount', 'has-not')
        );
        // 模拟未心跳，累计计数
        PushServer::setConnectionProperty($connection, 'clientNotSendPingCount', 1);
        // 断言检测心跳累计为1
        $this->assertEquals(
            1, PushServer::getConnectionProperty($connection, 'clientNotSendPingCount', 'has-not')
        );
        // 断言检测事件初始为null
        $this->assertNull($this->getPushServer()->getLastEvent());
        // 设置回执buffer null
        $connection->setSendBuffer(null);
        // 断言检测回执buffer为null
        $this->assertNull($connection->getSendBuffer());
        // 检测连接通道列表
        $this->assertEquals([], PushServer::getConnectionProperty($connection, 'channels', []));
        // 检测进程通道列表
        $this->assertNull(PushServer::getChannels(
            PushServer::getConnectionProperty($connection, 'appKey'),
            "public-channel-$key",
            PushServer::getConnectionProperty($connection, 'socketId')
        ));

        // 模拟发送 subscribe
        $this->getPushServer()->onMessage($connection, $this->ArrayToJson([
            'event' => EVENT_SUBSCRIBE,
            'data'  => [
                'channel' => "public-channel-$key",
            ]
        ]));
        // 断言检测心跳计数为0
        $this->assertEquals(
            0, PushServer::getConnectionProperty($connection, 'clientNotSendPingCount', 'has-not')
        );
        // 断言检测 Subscribe
        $this->assertTrue($this->getPushServer()->getLastEvent() instanceof Subscribe);
        // 断言检测回执 EVENT_SUBSCRIPTION_SUCCEEDED
        $data = @json_decode($connection->getSendBuffer(), true) ?: [];
        $this->assertEquals(EVENT_SUBSCRIPTION_SUCCEEDED, $data['event'] ?? null);
        $this->assertEquals([], $data['data'] ?? null);
        // 检测连接通道列表
        $this->assertEquals([
            "public-channel-$key" => 'public'
        ], PushServer::getConnectionProperty($connection, 'channels', []));
        // 检测进程通道列表
        $this->assertEquals(
            $socketId = PushServer::getConnectionProperty($connection, 'socketId'),
            PushServer::getChannels(
                PushServer::getConnectionProperty($connection, 'appKey'),
                "public-channel-$key",
                $socketId
            )
        );
        // 设置回执buffer null
        $connection->setSendBuffer(null);
        // 模拟未心跳，累计计数
        PushServer::setConnectionProperty($connection, 'clientNotSendPingCount', 1);
        // 初始化连接通道列表
        PushServer::setConnectionProperty($connection, 'channels', []);
        // 初始化进程通道列表
        PushServer::setConnections([]);

        // 模拟发送 subscribe
        $this->getPushServer()->onMessage($connection, $this->ArrayToJson([
            'event' => EVENT_SUBSCRIBE,
            'data'  => [
                'channel'      => "presence-channel-$key",
                'channel_data' => [
                    'user_id'   => $userId = 'acb',
                    'user_info' => $userInfoJson = json_encode([
                        'name' => 'chaz6chez',
                        'sex'  => 'male'
                    ], JSON_UNESCAPED_UNICODE)
                ]
            ]
        ]));
        // 断言检测心跳计数为0
        $this->assertEquals(
            0, PushServer::getConnectionProperty($connection, 'clientNotSendPingCount', 'has-not')
        );
        // 断言检测 Subscribe
        $this->assertTrue($this->getPushServer()->getLastEvent() instanceof Subscribe);
        // 断言检测回执 EVENT_SUBSCRIPTION_SUCCEEDED
        $data = @json_decode($connection->getSendBuffer(), true) ?: [];
        $this->assertEquals(EVENT_SUBSCRIPTION_SUCCEEDED, $data['event'] ?? null);
        $this->assertEquals([
            'presence' => [
                'count' => 1,
                'ids'   => [$userId],
                'hash'  => [
                    $userId => $userInfoJson
                ]
            ]
        ], $data['data'] ?? null);
        // 检测连接通道列表
        $this->assertEquals([
            "presence-channel-$key" => 'presence'
        ], PushServer::getConnectionProperty($connection, 'channels', []));
        // 检测进程通道列表
        $this->assertEquals(
            $socketId = PushServer::getConnectionProperty($connection, 'socketId'),
            PushServer::getChannels(
                PushServer::getConnectionProperty($connection, 'appKey'),
                "presence-channel-$key",
                $socketId
            )
        );
        // 设置回执buffer null
        $connection->setSendBuffer(null);
        // 模拟未心跳，累计计数
        PushServer::setConnectionProperty($connection, 'clientNotSendPingCount', 1);
        // 初始化连接通道列表
        PushServer::setConnectionProperty($connection, 'channels', []);
        // 初始化进程通道列表
        PushServer::setConnections([]);
    }


    public function testPushServerEventUnsubscribe()
    {
        $key = __FUNCTION__;
        // 初始化一个mock tcp连接
        $connection = new MockTcpConnection();
        // 模拟onConnect
        $this->getPushServer()->onConnect($connection);
        // 模拟调用$connection->onWebSocketConnect
        call_user_func(PushServer::getConnectionProperty($connection, 'onWebSocketConnect'), $connection, $this->getWebsocketHeader());
        // 模拟发送 subscribe
        $this->getPushServer()->onMessage($connection, $this->ArrayToJson([
            'event' => EVENT_SUBSCRIBE,
            'data'  => [
                'channel' => "public-channel-$key",
            ]
        ]));
        // 断言检测心跳计数为0
        $this->assertEquals(
            0, PushServer::getConnectionProperty($connection, 'clientNotSendPingCount', 'has-not')
        );
        // 模拟未心跳，累计计数
        PushServer::setConnectionProperty($connection, 'clientNotSendPingCount', 1);
        // 断言检测心跳累计为1
        $this->assertEquals(
            1, PushServer::getConnectionProperty($connection, 'clientNotSendPingCount', 'has-not')
        );
        // 设置事件初始
        $this->getPushServer()->setLastEvent(null);
        // 断言检测事件初始为null
        $this->assertNull($this->getPushServer()->getLastEvent());
        // 设置回执buffer null
        $connection->setSendBuffer(null);
        // 断言检测回执buffer为null
        $this->assertNull($connection->getSendBuffer());
        // 检测连接通道列表
        $this->assertEquals([
            "public-channel-$key" => 'public'
        ], PushServer::getConnectionProperty($connection, 'channels', []));
        // 检测进程通道列表
        $this->assertEquals(
            $socketId = PushServer::getConnectionProperty($connection, 'socketId'),
            PushServer::getChannels(
                PushServer::getConnectionProperty($connection, 'appKey'),
                "public-channel-$key",
                $socketId
            )
        );

        // 模拟发送 subscribe
        $this->getPushServer()->onMessage($connection, $this->ArrayToJson([
            'event' => EVENT_UNSUBSCRIBE,
            'data'  => [
                'channel' => "public-channel-$key",
            ]
        ]));

        // 断言检测心跳计数为0
        $this->assertEquals(
            0, PushServer::getConnectionProperty($connection, 'clientNotSendPingCount', 'has-not')
        );
        // 断言检测 Unsubscribe
        $this->assertTrue($this->getPushServer()->getLastEvent() instanceof Unsubscribe);
        // 断言检测回执 EVENT_UNSUBSCRIPTION_SUCCEEDED
        $data = @json_decode($connection->getSendBuffer(), true) ?: [];
        $this->assertEquals(EVENT_UNSUBSCRIPTION_SUCCEEDED, $data['event'] ?? null);
        $this->assertEquals([], $data['data'] ?? null);
        // 检测连接通道列表
        $this->assertEquals([], PushServer::getConnectionProperty($connection, 'channels', []));
        // 检测进程通道列表
        $this->assertNull(
            PushServer::getChannels(
                PushServer::getConnectionProperty($connection, 'appKey'),
                "public-channel-$key",
                PushServer::getConnectionProperty($connection, 'socketId')
            )
        );
    }


    public function testPushServerEventClientEventError()
    {
        $key = __FUNCTION__;
        // 初始化一个mock tcp连接
        $connection = new MockTcpConnection();
        // 模拟onConnect
        $this->getPushServer()->onConnect($connection);
        // 模拟调用$connection->onWebSocketConnect
        call_user_func(PushServer::getConnectionProperty($connection, 'onWebSocketConnect'), $connection, $this->getWebsocketHeader());
        // 断言检测心跳计数为0
        $this->assertEquals(
            0, PushServer::getConnectionProperty($connection, 'clientNotSendPingCount', 'has-not')
        );
        // 模拟未心跳，累计计数
        PushServer::setConnectionProperty($connection, 'clientNotSendPingCount', 1);
        // 断言检测心跳累计为1
        $this->assertEquals(
            1, PushServer::getConnectionProperty($connection, 'clientNotSendPingCount', 'has-not')
        );
        // 断言检测事件初始为null
        $this->assertNull($this->getPushServer()->getLastEvent());
        // 设置回执buffer null
        $connection->setSendBuffer(null);
        // 断言检测回执buffer为null
        $this->assertNull($connection->getSendBuffer());

        // 模拟发送 非client-事件
        $this->getPushServer()->onMessage($connection, $this->ArrayToJson([
            'event' => 'abc',
        ]));
        // 断言检测心跳
        $this->assertEquals(
            0, PushServer::getConnectionProperty($connection, 'clientNotSendPingCount', 'has-not')
        );
        // 断言检测
        $this->assertTrue($this->getPushServer()->getLastEvent() instanceof ClientEvent);
        // 断言检测回执
        $data = @json_decode($connection->getSendBuffer(), true) ?: [];
        $this->assertEquals(EVENT_ERROR, $data['event'] ?? null);
        $this->assertEquals([
            'code'      => '403',
            'message'   => 'Client rejected - client events must be prefixed by \'client-\''
        ], $data['data'] ?? null);
        // 设置回执buffer null
        $connection->setSendBuffer(null);
        // 初始化事件
        $this->getPushServer()->setLastEvent(null);
        // 模拟未心跳，累计计数
        PushServer::setConnectionProperty($connection, 'clientNotSendPingCount', 1);

        // 模拟发送 client-事件 no channel
        $this->getPushServer()->onMessage($connection, $this->ArrayToJson([
            'event' => 'client-test',
        ]));
        // 断言检测心跳
        $this->assertEquals(
            0, PushServer::getConnectionProperty($connection, 'clientNotSendPingCount', 'has-not')
        );
        // 断言检测
        $this->assertTrue($this->getPushServer()->getLastEvent() instanceof ClientEvent);
        // 断言检测回执
        $data = @json_decode($connection->getSendBuffer(), true) ?: [];
        $this->assertEquals(EVENT_ERROR, $data['event'] ?? null);
        $this->assertEquals([
            'code'      => '404',
            'message'   => 'Client error - Bad channel'
        ], $data['data'] ?? null);
        // 设置回执buffer null
        $connection->setSendBuffer(null);
        // 初始化事件
        $this->getPushServer()->setLastEvent(null);
        // 模拟未心跳，累计计数
        PushServer::setConnectionProperty($connection, 'clientNotSendPingCount', 1);

        // 模拟发送 client-事件 no data
        $this->getPushServer()->onMessage($connection, $this->ArrayToJson([
            'event'   => 'client-test',
            'channel' => "public-channel-$key",
        ]));
        // 断言检测心跳
        $this->assertEquals(
            0, PushServer::getConnectionProperty($connection, 'clientNotSendPingCount', 'has-not')
        );
        // 断言检测
        $this->assertTrue($this->getPushServer()->getLastEvent() instanceof ClientEvent);
        // 断言检测回执
        $data = @json_decode($connection->getSendBuffer(), true) ?: [];
        $this->assertEquals(EVENT_ERROR, $data['event'] ?? null);
        $this->assertEquals([
            'code'      => '400',
            'message'   => 'Client error - Empty data'
        ], $data['data'] ?? null);
        // 设置回执buffer null
        $connection->setSendBuffer(null);
        // 初始化事件
        $this->getPushServer()->setLastEvent(null);
        // 模拟未心跳，累计计数
        PushServer::setConnectionProperty($connection, 'clientNotSendPingCount', 1);

        // 模拟发送 client-事件 no subscribe channel
        $this->getPushServer()->onMessage($connection, $this->ArrayToJson([
            'event'   => 'client-test',
            'channel' => "public-channel-$key",
            'data'    => [
                'message' => 'test'
            ]
        ]));
        // 断言检测心跳
        $this->assertEquals(
            0, PushServer::getConnectionProperty($connection, 'clientNotSendPingCount', 'has-not')
        );
        // 断言检测
        $this->assertTrue($this->getPushServer()->getLastEvent() instanceof ClientEvent);
        // 断言检测回执
        $data = @json_decode($connection->getSendBuffer(), true) ?: [];
        $this->assertEquals(EVENT_ERROR, $data['event'] ?? null);
        $this->assertEquals([
            'code'      => '403',
            'message'   => 'Client rejected - you didn\'t subscribe this channel'
        ], $data['data'] ?? null);
        // 设置回执buffer null
        $connection->setSendBuffer(null);
        // 初始化事件
        $this->getPushServer()->setLastEvent(null);
        // 模拟未心跳，累计计数
        PushServer::setConnectionProperty($connection, 'clientNotSendPingCount', 1);
    }


    public function testPushServerEventClientEventFailure()
    {
        $key = __FUNCTION__;
        // 初始化一个mock tcp连接
        $connection = new MockTcpConnection();
        // 模拟onConnect
        $this->getPushServer()->onConnect($connection);
        // 模拟调用$connection->onWebSocketConnect
        call_user_func(PushServer::getConnectionProperty($connection, 'onWebSocketConnect'), $connection, $this->getWebsocketHeader());
        // 模拟发送 subscribe
        $this->getPushServer()->onMessage($connection, $this->ArrayToJson([
            'event' => EVENT_SUBSCRIBE,
            'data'  => [
                'channel' => "public-channel-$key",
            ]
        ]));
        // 断言检测心跳计数为0
        $this->assertEquals(
            0, PushServer::getConnectionProperty($connection, 'clientNotSendPingCount', 'has-not')
        );
        // 模拟未心跳，累计计数
        PushServer::setConnectionProperty($connection, 'clientNotSendPingCount', 1);
        // 断言检测心跳累计为1
        $this->assertEquals(
            1, PushServer::getConnectionProperty($connection, 'clientNotSendPingCount', 'has-not')
        );
        // 设置事件初始
        $this->getPushServer()->setLastEvent(null);
        // 断言检测事件初始为null
        $this->assertNull($this->getPushServer()->getLastEvent());
        // 设置回执buffer null
        $connection->setSendBuffer(null);
        // 断言检测回执buffer为null
        $this->assertNull($connection->getSendBuffer());
        // 检测连接通道列表
        $this->assertEquals([
            "public-channel-$key" => 'public'
        ], PushServer::getConnectionProperty($connection, 'channels', []));
        // 检测进程通道列表
        $this->assertEquals(
            $socketId = PushServer::getConnectionProperty($connection, 'socketId'),
            PushServer::getChannels(
                PushServer::getConnectionProperty($connection, 'appKey'),
                "public-channel-$key",
                $socketId
            )
        );

        // 模拟发送 client-事件
        $this->getPushServer()->onMessage($connection, $this->ArrayToJson([
            'event'   => 'client-test',
            'channel' => "public-channel-$key",
            'data'    => [
                'message' => 'test'
            ]
        ]));
        // 断言检测心跳
        $this->assertEquals(
            0, PushServer::getConnectionProperty($connection, 'clientNotSendPingCount', 'has-not')
        );
        // 断言检测
        $this->assertTrue($this->getPushServer()->getLastEvent() instanceof ClientEvent);
        // 断言检测回执
        $data = @json_decode($connection->getSendBuffer(), true) ?: [];
        $this->assertEquals(EVENT_ERROR, $data['event'] ?? null);
        $this->assertEquals([
            'code'      => '403',
            'message'   => 'Client rejected - only supported on private and presence channels'
        ], $data['data'] ?? null);
    }


    public function testPushServerEventClientEventSuccess()
    {
        $key = __FUNCTION__;
        // 初始化一个mock tcp连接
        $connection = new MockTcpConnection();
        // 模拟onConnect
        $this->getPushServer()->onConnect($connection);
        // 模拟调用$connection->onWebSocketConnect
        call_user_func(PushServer::getConnectionProperty($connection, 'onWebSocketConnect'), $connection, $this->getWebsocketHeader());
        // 模拟发送 subscribe
        $this->getPushServer()->onMessage($connection, $this->ArrayToJson([
            'event' => EVENT_SUBSCRIBE,
            'data'  => [
                'channel' => "private-channel-$key",
            ]
        ]));
        // 模拟发送 subscribe
        $this->getPushServer()->onMessage($connection, $this->ArrayToJson([
            'event' => EVENT_SUBSCRIBE,
            'data'  => [
                'channel'      => "presence-channel-$key",
                'channel_data' => [
                    'user_id'   => '1',
                    'user_info' => json_encode([
                        'name' => 'chaz6chez',
                        'sex'  => 'male'
                    ], JSON_UNESCAPED_UNICODE)
                ]
            ]
        ]));
        // 断言检测心跳计数为0
        $this->assertEquals(
            0, PushServer::getConnectionProperty($connection, 'clientNotSendPingCount', 'has-not')
        );
        // 模拟未心跳，累计计数
        PushServer::setConnectionProperty($connection, 'clientNotSendPingCount', 1);
        // 断言检测心跳累计为1
        $this->assertEquals(
            1, PushServer::getConnectionProperty($connection, 'clientNotSendPingCount', 'has-not')
        );
        // 设置事件初始
        $this->getPushServer()->setLastEvent(null);
        // 断言检测事件初始为null
        $this->assertNull($this->getPushServer()->getLastEvent());
        // 设置回执buffer null
        $connection->setSendBuffer(null);
        // 断言检测回执buffer为null
        $this->assertNull($connection->getSendBuffer());
        // 检测连接通道列表
        $this->assertEquals([
            "private-channel-$key"   => 'private',
            "presence-channel-$key"  => 'presence'
        ], PushServer::getConnectionProperty($connection, 'channels', []));
        // 检测进程通道列表
        $this->assertEquals(
            $socketId = PushServer::getConnectionProperty($connection, 'socketId'),
            PushServer::getChannels(
                PushServer::getConnectionProperty($connection, 'appKey'),
                "private-channel-$key",
                $socketId
            )
        );
        // 检测进程通道列表
        $this->assertEquals(
            $socketId = PushServer::getConnectionProperty($connection, 'socketId'),
            PushServer::getChannels(
                PushServer::getConnectionProperty($connection, 'appKey'),
                "presence-channel-$key",
                $socketId
            )
        );

        // 模拟发送 client-事件 private通道
        $this->getPushServer()->onMessage($connection, $this->ArrayToJson([
            'event'   => 'client-test',
            'channel' => "private-channel-$key",
            'data'    => [
                'message' => 'test'
            ]
        ]));
        // 断言检测心跳
        $this->assertEquals(
            0, PushServer::getConnectionProperty($connection, 'clientNotSendPingCount', 'has-not')
        );
        // 断言检测
        $this->assertTrue($this->getPushServer()->getLastEvent() instanceof ClientEvent);
        // 断言检测回执，成功后由广播推送，所以这里回执为null
        $this->assertNull($connection->getSendBuffer());
        // 事件初始化
        $this->getPushServer()->setLastEvent(null);

        // 模拟发送 client-事件 presence通道
        $this->getPushServer()->onMessage($connection, $this->ArrayToJson([
            'event'   => 'client-test',
            'channel' => "presence-channel-$key",
            'data'    => [
                'message' => 'test'
            ]
        ]));
        // 断言检测心跳
        $this->assertEquals(
            0, PushServer::getConnectionProperty($connection, 'clientNotSendPingCount', 'has-not')
        );
        // 断言检测
        $this->assertTrue($this->getPushServer()->getLastEvent() instanceof ClientEvent);
        // 断言检测回执，成功后由广播推送，所以这里回执为null
        $this->assertNull($connection->getSendBuffer());
        // 事件初始化
        $this->getPushServer()->setLastEvent(null);
    }
}
