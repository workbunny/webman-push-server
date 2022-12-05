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

use Closure;
use Exception;
use Tests\MockClass\MockRedisStream;
use Tests\MockClass\MockTcpConnection;
use Workbunny\WebmanPushServer\HookServer;

class ServerBaseTest extends BaseTest
{
    /**
     * 测试server初始化
     * @throws Exception
     */
    public function testServerInit(){
        $this->setServer();
        $this->expectOutputString('workbunny/webman-push-server/api-service listen: http://0.0.0.0:8002' . PHP_EOL);
    }

    /**
     * 测试OnMessage
     * @covers \Workbunny\WebmanPushServer\Server::onMessage
     * @covers \Workbunny\WebmanPushServer\Server::_setConnectionProperty
     * @covers \Workbunny\WebmanPushServer\Server::_getConnectionProperty
     * @throws Exception
     */
    public function testServerOnMessageWithNormalStringData(){
        $this->setServer(true);

        // normal string
        $mockConnection = new MockTcpConnection();
        $this->getServer()->onMessage($mockConnection, '');

        $this->assertEquals(true, isset($mockConnection->clientNotSendPingCount));
        $this->assertEquals(0, $this->getServer()->_getConnectionProperty($mockConnection, 'clientNotSendPingCount'));

        // bool
        $mockConnection = new MockTcpConnection();
        $this->getServer()->onMessage($mockConnection, true);

        $this->assertEquals(false, isset($mockConnection->clientNotSendPingCount));
        $this->assertEquals(null, $this->getServer()->_getConnectionProperty($mockConnection, 'clientNotSendPingCount'));

        // array
        $mockConnection = new MockTcpConnection();
        $this->getServer()->onMessage($mockConnection, []);

        $this->assertEquals(false, isset($mockConnection->clientNotSendPingCount));
        $this->assertEquals(null, $this->getServer()->_getConnectionProperty($mockConnection, 'clientNotSendPingCount'));

        // object
        $mockConnection = new MockTcpConnection();
        $this->getServer()->onMessage($mockConnection, $mockConnection);

        $this->assertEquals(false, isset($mockConnection->clientNotSendPingCount));
        $this->assertEquals(null, $this->getServer()->_getConnectionProperty($mockConnection, 'clientNotSendPingCount'));
    }

    /**
     * 测试成功的onConnect
     * @covers \Workbunny\WebmanPushServer\Server::onConnect
     * @covers \Workbunny\WebmanPushServer\Server::_setConnectionProperty
     * @covers \Workbunny\WebmanPushServer\Server::_getConnectionProperty
     * @covers \Workbunny\WebmanPushServer\Server::_getConnection
     * @covers \Workbunny\WebmanPushServer\Server::_setConnection
     * @covers \Workbunny\WebmanPushServer\HookServer::publish
     * @throws Exception
     */
    public function testServerOnConnectSuccessful()
    {
        $this->setServer(true);

        $mockConnection = new MockTcpConnection();

        // 手动触发 onConnect 回调
        $this->getServer()->onConnect($mockConnection);
        $this->assertEquals(
            true,
            ($onWebSocketConnect = $this->getServer()->_getConnectionProperty($mockConnection, 'onWebSocketConnect')) instanceof Closure
        );

        // 手动触发 onWebSocketConnect 回调
        $onWebSocketConnect($mockConnection, "/app/{$this->_auth_key}?{$this->_query_string}");
        $this->assertEquals(0, $this->getServer()->_getConnectionProperty($mockConnection, 'clientNotSendPingCount'));
        $this->assertEquals($this->_auth_key, $appKey = $this->getServer()->_getConnectionProperty($mockConnection, 'appKey', 'unknown'));
        $this->assertEquals($this->_query_string, $this->getServer()->_getConnectionProperty($mockConnection, 'queryString', 'unknown'));
        $this->assertNotNull($this->getServer()->_getConnectionProperty($mockConnection, 'socketId'));
        $this->assertEquals(['' => ''], $this->getServer()->_getConnectionProperty($mockConnection, 'channels'));
        $this->assertEquals($mockConnection, $this->getServer()->_getConnection($mockConnection, $appKey, ''));

        /** @var MockRedisStream $storage */
        $storage = HookServer::getStorage();
        // 队列新增一条数据
        $this->assertCount(1, $queue = $storage->getStreams()['workbunny:webman-push-server:webhook-stream'] ?? []);
        // 队列包含一条server_event事件
        $this->assertContains('server_event', array_column($queue, 'name'));
    }

    /**
     * 测试 onConnect触发Invalid header错误
     * @covers \Workbunny\WebmanPushServer\Server::onConnect
     * @covers \Workbunny\WebmanPushServer\Server::_setConnectionProperty
     * @covers \Workbunny\WebmanPushServer\Server::_getConnectionProperty
     * @covers \Workbunny\WebmanPushServer\Server::_getConnection
     * @covers \Workbunny\WebmanPushServer\Server::_setConnection
     * @covers \Workbunny\WebmanPushServer\HookServer::publish
     * @throws Exception
     */
    public function testServerOnConnectInvalidHeaderError()
    {
        $this->setServer(true);

        $mockConnection = new MockTcpConnection();

        // 手动触发 onConnect 回调
        $this->getServer()->onConnect($mockConnection);
        $this->assertEquals(
            true,
            ($onWebSocketConnect = $this->getServer()->_getConnectionProperty($mockConnection, 'onWebSocketConnect')) instanceof Closure
        );

        // 手动触发 onWebSocketConnect 回调
        $onWebSocketConnect($mockConnection, "12345");
        $this->assertEquals(0, $this->getServer()->_getConnectionProperty($mockConnection, 'clientNotSendPingCount'));
        $this->assertEquals(true, $mockConnection->isPaused());
        $this->assertEquals([], $mockConnection->getSendBuffer());
    }

    /**
     * 测试OnMessage
     * @covers \Workbunny\WebmanPushServer\Server::onMessage
     * @covers \Workbunny\WebmanPushServer\Server::_setConnectionProperty
     * @covers \Workbunny\WebmanPushServer\Server::_getConnectionProperty
     * @throws Exception
     */
    public function testServerOnMessageWithBoolData(){
        $this->setServer(true);

        // bool
        $mockConnection = new MockTcpConnection();
        $this->getServer()->onMessage($mockConnection, true);

        $this->assertEquals(false, isset($mockConnection->clientNotSendPingCount));
        $this->assertEquals(null, $this->getServer()->_getConnectionProperty($mockConnection, 'clientNotSendPingCount'));
    }

    /**
     * 测试OnMessage
     * @covers \Workbunny\WebmanPushServer\Server::onMessage
     * @covers \Workbunny\WebmanPushServer\Server::_setConnectionProperty
     * @covers \Workbunny\WebmanPushServer\Server::_getConnectionProperty
     * @throws Exception
     */
    public function testServerOnMessageWithArrayData(){
        $this->setServer(true);

        // array
        $mockConnection = new MockTcpConnection();
        $this->getServer()->onMessage($mockConnection, []);

        $this->assertEquals(false, isset($mockConnection->clientNotSendPingCount));
        $this->assertEquals(null, $this->getServer()->_getConnectionProperty($mockConnection, 'clientNotSendPingCount'));
    }

    /**
     * 测试OnMessage
     * @covers \Workbunny\WebmanPushServer\Server::onMessage
     * @covers \Workbunny\WebmanPushServer\Server::_setConnectionProperty
     * @covers \Workbunny\WebmanPushServer\Server::_getConnectionProperty
     * @throws Exception
     */
    public function testServerOnMessageWithObjectData(){
        $this->setServer(true);

        // object
        $mockConnection = new MockTcpConnection();
        $this->getServer()->onMessage($mockConnection, $mockConnection);

        $this->assertEquals(false, isset($mockConnection->clientNotSendPingCount));
        $this->assertEquals(null, $this->getServer()->_getConnectionProperty($mockConnection, 'clientNotSendPingCount'));
    }
}
