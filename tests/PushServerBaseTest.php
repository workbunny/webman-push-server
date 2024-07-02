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

use Exception;
use Tests\MockClass\MockTcpConnection;
use Workbunny\WebmanPushServer\Events\Ping;
use Workbunny\WebmanPushServer\PushServer;
use const Workbunny\WebmanPushServer\EVENT_CONNECTION_ESTABLISHED;
use const Workbunny\WebmanPushServer\EVENT_PONG;

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
        // 模拟onConnect
        $this->getPushServer()->onConnect($connection);
        // 断言判定
        $this->assertEquals(
            PushServer::$unknownTag, PushServer::getConnectionProperty($connection, 'appKey', 'has-not')
        );
        $this->assertEquals(
            0, PushServer::getConnectionProperty($connection, 'clientNotSendPingCount', 'has-not')
        );
        $this->assertNotEquals(
            'has-not', PushServer::getConnectionProperty($connection, 'socketId', 'has-not')
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
        $this->assertEquals(
            'workbunny', PushServer::getConnectionProperty($connection, 'appKey', 'has-not')
        );
        $this->assertEquals(
            0, PushServer::getConnectionProperty($connection, 'clientNotSendPingCount', 'has-not')
        );
        $this->assertEquals(
            'protocol=7&client=js&version=3.2.4&flash=false', PushServer::getConnectionProperty($connection, 'queryString', 'has-not')
        );
        $this->assertNotEquals(
            'has-not', PushServer::getConnectionProperty($connection, 'socketId', 'has-not')
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
        // 断言检测初始buffer为空
        $this->assertTrue(!$connection->getSendBuffer());
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

}
