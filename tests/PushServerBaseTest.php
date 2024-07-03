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
use Workbunny\WebmanPushServer\PushServer;
use const Workbunny\WebmanPushServer\EVENT_CONNECTION_ESTABLISHED;
use const Workbunny\WebmanPushServer\EVENT_PONG;
use const Workbunny\WebmanPushServer\EVENT_UNSUBSCRIPTION_SUCCEEDED;

class PushServerBaseTest extends BaseTestCase
{
    /**
     * @return void
     */
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

    /**
     * @return void
     */
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

    /**
     * @return void
     */
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
        $this->assertEquals(
            null, PushServer::getConnection($appKey, $socketId)
        );
        // 断言检测回执buffer
        $this->assertEquals(null, $connection->getSendBuffer());
        $this->assertEquals([], PushServer::getConnectionProperty($connection, 'channels', []));
    }

    /**
     * @return void
     */
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

//    public function testPushServerHeartbeat()
//    {
//        // 初始化一个mock tcp连接
//        $connection1 = new MockTcpConnection();
//        $connection2 = new MockTcpConnection();
//        // 模拟创建链接
//        $this->getPushServer()->onConnect($connection1);
//        $this->getPushServer()->onConnect($connection2);
//        // 判断链接心跳计数
//        $this->assertEquals(
//            0, PushServer::getConnectionProperty($connection1, 'clientNotSendPingCount', 'has-not')
//        );
//        $this->assertEquals(
//            0, PushServer::getConnectionProperty($connection2, 'clientNotSendPingCount', 'has-not')
//        );
//        $this->assertCount(2, PushServer::getConnections()[PushServer::$unknownTag] ?? []);
//        // 模拟一次心跳检测
//        call_user_func([PushServer::class, '_heartbeatChecker']);
//        // 判断链接心跳计数
//        $this->assertEquals(
//            1, PushServer::getConnectionProperty($connection1, 'clientNotSendPingCount', 'has-not')
//        );
//        $this->assertEquals(
//            1, PushServer::getConnectionProperty($connection2, 'clientNotSendPingCount', 'has-not')
//        );
//        $this->assertCount(2, PushServer::getConnections()[PushServer::$unknownTag] ?? []);
//        // 为连接1模拟一次ping
//        $this->getPushServer()->onMessage($connection1, '{"event":"pusher:ping"}');
//        // 判断链接心跳计数
//        $this->assertEquals(
//            0, PushServer::getConnectionProperty($connection1, 'clientNotSendPingCount', 'has-not')
//        );
//        $this->assertEquals(
//            1, PushServer::getConnectionProperty($connection2, 'clientNotSendPingCount', 'has-not')
//        );
//        $this->assertCount(2, PushServer::getConnections()[PushServer::$unknownTag] ?? []);
//        // 模拟一次心跳检测
//        call_user_func([PushServer::class, '_heartbeatChecker']);
//        // 判断链接心跳计数
//        $this->assertEquals(
//            1, PushServer::getConnectionProperty($connection1, 'clientNotSendPingCount', 'has-not')
//        );
//        $this->assertEquals(
//            2, PushServer::getConnectionProperty($connection2, 'clientNotSendPingCount', 'has-not')
//        );
//        $this->assertCount(2, PushServer::getConnections()[PushServer::$unknownTag] ?? []);
//        // 模拟一次心跳检测
//        call_user_func([PushServer::class, '_heartbeatChecker']);
//        // 判断链接心跳计数
//        $this->assertEquals(
//            2, PushServer::getConnectionProperty($connection1, 'clientNotSendPingCount', 'has-not')
//        );
//        $this->assertEquals(
//            'has-not', PushServer::getConnectionProperty($connection2, 'clientNotSendPingCount', 'has-not')
//        );
//        $this->assertCount(1, PushServer::getConnections()[PushServer::$unknownTag] ?? []);
//    }

}
