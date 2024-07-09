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
use Workbunny\WebmanPushServer\Events\Ping;
use Workbunny\WebmanPushServer\Events\Subscribe;
use Workbunny\WebmanPushServer\PublishTypes\AbstractPublishType;
use Workbunny\WebmanPushServer\PushServer;
use const Workbunny\WebmanPushServer\EVENT_CONNECTION_ESTABLISHED;
use const Workbunny\WebmanPushServer\EVENT_ERROR;
use const Workbunny\WebmanPushServer\EVENT_PONG;
use const Workbunny\WebmanPushServer\EVENT_TERMINATE_CONNECTION;
use const Workbunny\WebmanPushServer\EVENT_UNSUBSCRIPTION_SUCCEEDED;

/**
 * @runTestsInSeparateProcesses
 */
class PushServerBaseTest extends BaseTestCase
{

    public function testPushServerOnConnect()
    {
        // 初始化一个mock tcp连接
        $connection = new MockTcpConnection();
        // 初始化判定
        $this->assertFalse(property_exists($connection, 'onWebSocketConnect'));
        $this->assertFalse(property_exists($connection, 'appKey'));
        $this->assertFalse(property_exists($connection, 'clientNotSendPingCount'));
        $this->assertFalse(property_exists($connection, 'queryString'));
        $this->assertFalse(property_exists($connection, 'socketId'));
        $this->assertFalse(property_exists($connection, 'channels'));
        $this->assertEquals([], PushServer::getConnections()[PushServer::$unknownTag] ?? []);
        // 模拟onConnect
        $this->getPushServer()->onConnect($connection);
        // 断言判定
        $this->assertEquals(
            PushServer::$unknownTag, $appKey = PushServer::getConnectionProperty($connection, 'appKey', 'has-not')
        );
        $this->assertEquals(
            0, PushServer::getConnectionProperty($connection, 'clientNotSendPingCount', 'has-not')
        );
        $this->assertNotEquals(
            'has-not', $socketId = PushServer::getConnectionProperty($connection, 'socketId', 'has-not')
        );
        $this->assertEquals(
            $connection, PushServer::getConnection($appKey, $socketId)
        );
        $this->assertNotEquals(
            '', PushServer::getConnectionProperty($connection, 'queryString', 'has-not')
        );
        $this->assertNotEquals(
            [], PushServer::getConnectionProperty($connection, 'channels', 'has-not')
        );
        $this->assertTrue(
            is_callable(PushServer::getConnectionProperty($connection, 'onWebSocketConnect', 'has-not'))
        );
        $this->assertTrue(!$connection->getSendBuffer());
        // 模拟调用$connection->onWebSocketConnect
        call_user_func(PushServer::getConnectionProperty($connection, 'onWebSocketConnect'), $connection, $this->getWebsocketHeader());
        // 断言判定
        $this->assertEquals([],PushServer::getConnections()[PushServer::$unknownTag] ?? []);
        $this->assertEquals(
            'workbunny', $appKey = PushServer::getConnectionProperty($connection, 'appKey', 'has-not')
        );
        $this->assertEquals(
            0, PushServer::getConnectionProperty($connection, 'clientNotSendPingCount', 'has-not')
        );
        $this->assertEquals(
            'protocol=7&client=js&version=3.2.4&flash=false', PushServer::getConnectionProperty($connection, 'queryString', 'has-not')
        );
        $this->assertNotEquals(
            'has-not', $socketId = PushServer::getConnectionProperty($connection, 'socketId', 'has-not')
        );
        $this->assertEquals(
            $connection, PushServer::getConnection($appKey, $socketId)
        );
        $this->assertEquals(
            [], PushServer::getConnectionProperty($connection, 'channels', 'has-not')
        );
        // EVENT_CONNECTION_ESTABLISHED事件回复
        $this->assertEquals(EVENT_CONNECTION_ESTABLISHED, @json_decode($connection->getSendBuffer(), true)['event'] ?? null);
    }


    public function testPushServerOnConnectInvalidAppError()
    {
        // 初始化一个mock tcp连接
        $connection = new MockTcpConnection();
        // 模拟onConnect
        $this->getPushServer()->onConnect($connection);
        // 模拟调用$connection->onWebSocketConnect
        call_user_func(PushServer::getConnectionProperty($connection, 'onWebSocketConnect'), $connection, '');
        // 断言检测心跳计数为0
        $this->assertEquals(
            0, PushServer::getConnectionProperty($connection, 'clientNotSendPingCount', 'has-not')
        );
        // 断言检测连接被暂停接收
        $this->assertTrue($connection->isPaused());
        // 断言检测错误信息的返回
        $data = @json_decode($connection->getSendBuffer(), true) ?: [];
        $this->assertEquals(EVENT_ERROR, $data['event'] ?? null);
        $this->assertEquals([
            'code'      => null,
            'message'   => 'Invalid app'
        ], $data['data'] ?? []);
    }


    public function testPushServerOnConnectInvalidAppKeyError()
    {
        // 初始化一个mock tcp连接
        $connection = new MockTcpConnection();
        // 模拟onConnect
        $this->getPushServer()->onConnect($connection);
        // 模拟调用$connection->onWebSocketConnect
        call_user_func(
            PushServer::getConnectionProperty($connection, 'onWebSocketConnect'),
            $connection,
            "GET /app/none?protocol=7&client=js&version=3.2.4&flash=false HTTP/1.1\r\nConnection: Upgrade\r\nUpgrade: websocket\r\n\r\n"
        );

        // 断言检测心跳计数为0
        $this->assertEquals(
            0, PushServer::getConnectionProperty($connection, 'clientNotSendPingCount', 'has-not')
        );
        // 断言检测连接被暂停接收
        $this->assertTrue($connection->isPaused());
        // 断言检测错误信息的返回
        $data = @json_decode($connection->getSendBuffer(), true) ?: [];
        $this->assertEquals(EVENT_ERROR, $data['event'] ?? null);
        $this->assertEquals([
            'code'      => null,
            'message'   => 'Invalid app_key'
        ], $data['data'] ?? []);
    }


    public function testPushServerOnMessage()
    {
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
        // EVENT_CONNECTION_ESTABLISHED事件回复
        $this->assertEquals(EVENT_CONNECTION_ESTABLISHED, @json_decode($connection->getSendBuffer(), true)['event'] ?? null);
        // 模拟心跳
        $this->getPushServer()->onMessage($connection, '{"event":"pusher:ping"}');
        // 断言检测心跳累计为0
        $this->assertEquals(
            0, PushServer::getConnectionProperty($connection, 'clientNotSendPingCount', 'has-not')
        );
        // 断言检测事件为ping
        $this->assertTrue(($this->getPushServer()->getLastEvent()) instanceof Ping);
        // 断言检测回执buffer为pong事件
        $this->assertEquals(EVENT_PONG, @json_decode($connection->getSendBuffer(), true)['event'] ?? null);
    }


    public function testPushServerOnMessageIllegalData(){
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

        // 模拟发送 非规范字符串
        $this->getPushServer()->onMessage($connection, 'abc');
        // 断言检测心跳
        $this->assertEquals(
            1, PushServer::getConnectionProperty($connection, 'clientNotSendPingCount', 'has-not')
        );
        // 断言检测事件
        $this->assertNull($this->getPushServer()->getLastEvent());
        // 断言检测错误信息的返回
        $data = @json_decode($connection->getSendBuffer(), true) ?: [];
        $this->assertEquals(EVENT_ERROR, $data['event'] ?? null);
        $this->assertEquals([
            'code'      => null,
            'message'   => 'Client event rejected - Unknown event'
        ], $data['data'] ?? []);
        // 初始化回执buffer
        $connection->setSendBuffer(null);

        // 模拟发送 int
        $this->getPushServer()->onMessage($connection, 1);
        // 断言检测心跳累计
        $this->assertEquals(
            1, PushServer::getConnectionProperty($connection, 'clientNotSendPingCount', 'has-not')
        );
        // 断言检测事件
        $this->assertNull($this->getPushServer()->getLastEvent());
        // 断言检测回执buffer为null
        $this->assertNull($connection->getSendBuffer());

        // 模拟发送 float
        $this->getPushServer()->onMessage($connection, 1.1111);
        // 断言检测心跳累计
        $this->assertEquals(
            1, PushServer::getConnectionProperty($connection, 'clientNotSendPingCount', 'has-not')
        );
        // 断言检测事件
        $this->assertNull($this->getPushServer()->getLastEvent());
        // 断言检测回执buffer为null
        $this->assertNull($connection->getSendBuffer());

        // 模拟发送 bool
        $this->getPushServer()->onMessage($connection, true);
        // 断言检测心跳累计
        $this->assertEquals(
            1, PushServer::getConnectionProperty($connection, 'clientNotSendPingCount', 'has-not')
        );
        // 断言检测事件
        $this->assertNull($this->getPushServer()->getLastEvent());
        // 断言检测回执buffer为null
        $this->assertNull($connection->getSendBuffer());

        // 模拟发送 array
        $this->getPushServer()->onMessage($connection, [
            'data' => 1
        ]);
        // 断言检测心跳累计
        $this->assertEquals(
            1, PushServer::getConnectionProperty($connection, 'clientNotSendPingCount', 'has-not')
        );
        // 断言检测事件
        $this->assertNull($this->getPushServer()->getLastEvent());
        // 断言检测回执buffer为null
        $this->assertNull($connection->getSendBuffer());

        // 模拟发送 object
        $this->getPushServer()->onMessage($connection, new \stdClass());
        // 断言检测心跳累计
        $this->assertEquals(
            1, PushServer::getConnectionProperty($connection, 'clientNotSendPingCount', 'has-not')
        );
        // 断言检测事件
        $this->assertNull($this->getPushServer()->getLastEvent());
        // 断言检测回执buffer为null
        $this->assertNull($connection->getSendBuffer());
    }


    public function testPushServerOnCloseHasChannel()
    {
        // 初始化一个mock tcp连接
        $connection = new MockTcpConnection();
        // 模拟onConnect
        $this->getPushServer()->onConnect($connection);
        // 模拟调用$connection->onWebSocketConnect
        call_user_func(PushServer::getConnectionProperty($connection, 'onWebSocketConnect'), $connection, $this->getWebsocketHeader());
        // 断言判定
        $this->assertEquals(
            'workbunny', $appKey = PushServer::getConnectionProperty($connection, 'appKey', 'has-not')
        );
        $this->assertNotEquals(
            'has-not', $socketId = PushServer::getConnectionProperty($connection, 'socketId', 'has-not')
        );
        $this->assertEquals(
            $connection, PushServer::getConnection($appKey, $socketId)
        );
        // 模拟订阅通道
        $this->getPushServer()->onMessage($connection, '{"event":"pusher:subscribe","data":{"channel":"public-test"}}');
        // 断言判定
        $this->assertTrue($this->getPushServer()->getLastEvent() instanceof Subscribe);
        $this->assertEquals([
            'public-test' => 'public'
        ], PushServer::getConnectionProperty($connection, 'channels', []));
        $this->assertEquals($socketId, PushServer::getChannels($appKey, 'public-test', $socketId));
        // 模拟onClose
        $this->getPushServer()->onClose($connection);
        // 断言判定
        $this->assertEquals(null, PushServer::getConnection($appKey, $socketId));
        // 断言检测回执buffer
        $this->assertEquals(EVENT_UNSUBSCRIPTION_SUCCEEDED, @json_decode($connection->getSendBuffer(), true)['event'] ?? null);
        $this->assertEquals([], PushServer::getConnectionProperty($connection, 'channels', []));
        $this->assertNull(PushServer::getChannels($appKey, 'public-test', $socketId));
    }


    public function testPushServerOnCloseHasNotChannel()
    {
        // 初始化一个mock tcp连接
        $connection = new MockTcpConnection();
        // 模拟onConnect
        $this->getPushServer()->onConnect($connection);
        // 模拟调用$connection->onWebSocketConnect
        call_user_func(PushServer::getConnectionProperty($connection, 'onWebSocketConnect'), $connection, $this->getWebsocketHeader());
        // 断言判定
        $this->assertEquals(
            'workbunny', $appKey = PushServer::getConnectionProperty($connection, 'appKey', 'has-not')
        );
        $this->assertNotEquals(
            'has-not', $socketId = PushServer::getConnectionProperty($connection, 'socketId', 'has-not')
        );
        $this->assertEquals(
            $connection, PushServer::getConnection($appKey, $socketId)
        );
        $this->assertEquals([], PushServer::getConnectionProperty($connection, 'channels', []));
        // 模拟回执buffer初始化
        $connection->setSendBuffer(null);
        // 模拟onClose
        $this->getPushServer()->onClose($connection);
        // 断言判定
        $this->assertNull(PushServer::getConnection($appKey, $socketId));
        // 断言检测回执buffer
        $this->assertNull($connection->getSendBuffer());
        $this->assertEquals([], PushServer::getConnectionProperty($connection, 'channels', []));
    }


    public function testPushServerHeartbeatChecker()
    {
        // 初始化一个mock tcp连接
        $connection1 = new MockTcpConnection();
        $connection2 = new MockTcpConnection();
        // 模拟创建链接
        $this->getPushServer()->onConnect($connection1);
        $this->getPushServer()->onConnect($connection2);
        $this->assertCount(2, PushServer::getConnections()[PushServer::$unknownTag] ?? []);
        // 判断链接心跳计数
        $this->assertEquals(
            0, PushServer::getConnectionProperty($connection1, 'clientNotSendPingCount', 'has-not')
        );
        $this->assertEquals(
            0, PushServer::getConnectionProperty($connection2, 'clientNotSendPingCount', 'has-not')
        );
        // 模拟一次心跳检测
        call_user_func([PushServer::class, '_heartbeatChecker']);
        $this->assertCount(2, PushServer::getConnections()[PushServer::$unknownTag] ?? []);
        // 判断链接心跳计数
        $this->assertEquals(
            1, PushServer::getConnectionProperty($connection1, 'clientNotSendPingCount', 'has-not')
        );
        $this->assertEquals(
            1, PushServer::getConnectionProperty($connection2, 'clientNotSendPingCount', 'has-not')
        );
        // 为连接1模拟一次ping
        $this->getPushServer()->onMessage($connection1, '{"event":"pusher:ping"}');
        $this->assertCount(2, PushServer::getConnections()[PushServer::$unknownTag] ?? []);
        // 判断链接心跳计数
        $this->assertEquals(
            0, PushServer::getConnectionProperty($connection1, 'clientNotSendPingCount', 'has-not')
        );
        $this->assertEquals(
            1, PushServer::getConnectionProperty($connection2, 'clientNotSendPingCount', 'has-not')
        );
        // 模拟一次心跳检测
        call_user_func([PushServer::class, '_heartbeatChecker']);
        $this->assertCount(2, PushServer::getConnections()[PushServer::$unknownTag] ?? []);
        // 判断链接心跳计数
        $this->assertEquals(
            1, PushServer::getConnectionProperty($connection1, 'clientNotSendPingCount', 'has-not')
        );
        $this->assertEquals(
            2, PushServer::getConnectionProperty($connection2, 'clientNotSendPingCount', 'has-not')
        );
        // 模拟一次心跳检测
        call_user_func([PushServer::class, '_heartbeatChecker']);
        $this->assertCount(1, PushServer::getConnections()[PushServer::$unknownTag] ?? []);
        // 判断链接心跳计数
        $this->assertEquals(
            2, PushServer::getConnectionProperty($connection1, 'clientNotSendPingCount', 'has-not')
        );
        // 连接2因为心跳次数超过阈值，所以不会累加
        $this->assertEquals(
            2, PushServer::getConnectionProperty($connection2, 'clientNotSendPingCount', 'has-not')
        );
        // 连接2因触发回收，所以接受一个销毁连接的事件
        $this->assertEquals(EVENT_TERMINATE_CONNECTION, @json_decode($connection2->getSendBuffer(), true)['event'] ?? null);
    }


    public function testPushServerSubscribeResponse()
    {
        // 初始化mock tcp连接
        $wsConnection = new MockTcpConnection();
        $tcpConnection = new MockTcpConnection();
        $channelConnection = new MockTcpConnection();
        // 模拟创建链接
        $this->getPushServer()->onConnect($wsConnection);
        $this->getPushServer()->onConnect($tcpConnection);
        $this->getPushServer()->onConnect($channelConnection);
        // 模拟调用$connection->onWebSocketConnect
        call_user_func(PushServer::getConnectionProperty($wsConnection, 'onWebSocketConnect'), $wsConnection, $this->getWebsocketHeader());
        call_user_func(PushServer::getConnectionProperty($channelConnection, 'onWebSocketConnect'), $channelConnection, $this->getWebsocketHeader());
        // 模拟订阅channel
        // 模拟订阅通道
        $this->getPushServer()->onMessage($channelConnection, '{"event":"pusher:subscribe","data":{"channel":"public-test"}}');
        // 初始化buffer
        $channelConnection->setSendBuffer(null);
        $tcpConnection->setSendBuffer(null);
        $wsConnection->setSendBuffer(null);
        // 断言检测回执buffer
        $this->assertNull($channelConnection->getSendBuffer());
        $this->assertNull($tcpConnection->getSendBuffer());
        $this->assertNull($wsConnection->getSendBuffer());

        // 模拟服务广播响应 非忽略的channel广播
        PushServer::_subscribeResponse(AbstractPublishType::PUBLISH_TYPE_CLIENT, [
            'appKey'  => PushServer::getConnectionProperty($channelConnection, 'appKey'),
            'event'   => EVENT_PONG,
            'channel' => 'public-test'
        ]);
        // 断言检测回执buffer 仅合法channel连接接收到广播回执
        $this->assertNull($tcpConnection->getSendBuffer());
        $this->assertNull($wsConnection->getSendBuffer());
        $this->assertEquals(EVENT_PONG, @json_decode($channelConnection->getSendBuffer(), true)['event'] ?? null);
        // 初始化buffer
        $channelConnection->setSendBuffer(null);
        $tcpConnection->setSendBuffer(null);
        $wsConnection->setSendBuffer(null);

        // 模拟服务广播响应 指定忽略socketId的channel广播
        PushServer::_subscribeResponse(AbstractPublishType::PUBLISH_TYPE_CLIENT, [
            'appKey'   => PushServer::getConnectionProperty($channelConnection, 'appKey'),
            'event'    => EVENT_PONG,
            'channel'  => 'public-test',
            'socketId' => PushServer::getConnectionProperty($channelConnection, 'socketId'),
        ]);
        // 断言检测回执buffer 所有连接不应接收到回执
        $this->assertNull($tcpConnection->getSendBuffer());
        $this->assertNull($wsConnection->getSendBuffer());
        $this->assertNull($channelConnection->getSendBuffer());
        // 初始化buffer
        $channelConnection->setSendBuffer(null);
        $tcpConnection->setSendBuffer(null);
        $wsConnection->setSendBuffer(null);

        // 模拟服务广播响应 向未知连接广播
        PushServer::_subscribeResponse(AbstractPublishType::PUBLISH_TYPE_CLIENT, [
            'appKey'   => PushServer::$unknownTag,
            'event'    => EVENT_PONG,
            'channel'  => 'public-test',
        ]);
        // 断言检测回执buffer 所有连接不应接收到回执
        $this->assertNull($tcpConnection->getSendBuffer());
        $this->assertNull($wsConnection->getSendBuffer());
        $this->assertNull($channelConnection->getSendBuffer());
        // 初始化buffer
        $channelConnection->setSendBuffer(null);
        $tcpConnection->setSendBuffer(null);
        $wsConnection->setSendBuffer(null);
    }

}
