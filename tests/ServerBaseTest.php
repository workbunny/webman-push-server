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
use Workbunny\WebmanPushServer\ApiService;
use Workbunny\WebmanPushServer\HookServer;
use Workbunny\WebmanPushServer\Server;
use Workbunny\WebmanPushServer\ServerInterface;

class ServerBaseTest extends BaseTestCase
{
    /**
     * 测试server初始化
     * @throws Exception
     */
    public function testServerInit(){
        $this->setServer();
        // 判断是否监听了api-service
        $this->expectOutputString('workbunny/webman-push-server/api-service listen: http://0.0.0.0:8002' . PHP_EOL);
        // 判断是否生成了api-service
        $this->assertArrayHasKey(ApiService::class, $this->getServer()::getServices());
        // 判断api-service是否是ServerInterface
        $this->assertTrue($this->getServer()::getServices(ApiService::class) instanceof ServerInterface);
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
        Server::setServer($this->getServer());

        // normal string
        $mockConnection = new MockTcpConnection();
        $this->getServer()->onMessage($mockConnection, '');

        $this->assertTrue(isset($mockConnection->clientNotSendPingCount));
        $this->assertEquals(0, $this->getServer()->_getConnectionProperty($mockConnection, 'clientNotSendPingCount'));

        // bool
        $mockConnection = new MockTcpConnection();
        $this->getServer()->onMessage($mockConnection, true);

        $this->assertFalse(isset($mockConnection->clientNotSendPingCount));
        $this->assertEquals(null, $this->getServer()->_getConnectionProperty($mockConnection, 'clientNotSendPingCount'));

        // array
        $mockConnection = new MockTcpConnection();
        $this->getServer()->onMessage($mockConnection, []);

        $this->assertFalse(isset($mockConnection->clientNotSendPingCount));
        $this->assertEquals(null, $this->getServer()->_getConnectionProperty($mockConnection, 'clientNotSendPingCount'));

        // object
        $mockConnection = new MockTcpConnection();
        $this->getServer()->onMessage($mockConnection, $mockConnection);

        $this->assertFalse(isset($mockConnection->clientNotSendPingCount));
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
        Server::setServer($this->getServer());

        $mockConnection = new MockTcpConnection();

        // 手动触发 onConnect 回调
        $this->getServer()->onConnect($mockConnection);
        $this->assertTrue(
            ($onWebSocketConnect = $this->getServer()->_getConnectionProperty($mockConnection, 'onWebSocketConnect')) instanceof Closure
        );

        // 手动触发 onWebSocketConnect 回调
        $onWebSocketConnect($mockConnection, $this->_websocket_header);
        $this->assertEquals(0, $this->getServer()->_getConnectionProperty($mockConnection, 'clientNotSendPingCount'));
        $this->assertEquals('workbunny', $appKey = $this->getServer()->_getConnectionProperty($mockConnection, 'appKey', 'unknown'));
        $this->assertEquals('protocol=7&client=js&version=3.2.4&flash=false', $this->getServer()->_getConnectionProperty($mockConnection, 'queryString', 'unknown'));
        $this->assertNotNull($this->getServer()->_getConnectionProperty($mockConnection, 'socketId'));
        $this->assertEquals(['' => ''], $this->getServer()->_getConnectionProperty($mockConnection, 'channels'));
        $this->assertEquals($mockConnection, $this->getServer()->_getConnection($mockConnection, $appKey, ''));

        $storage = HookServer::getStorage();
        // 队列新增一条数据
        $this->assertEquals(1, $storage->exists('workbunny:webman-push-server:webhook-stream'));
        // 队列包含一条server_event事件
        $this->assertContains('server_event', array_column($storage->xRead([
            'workbunny:webman-push-server:webhook-stream' => '0-0'
        ], -1, 1)['workbunny:webman-push-server:webhook-stream'] ?? [], 'name'));
        // 移除队列
        $storage->del('workbunny:webman-push-server:webhook-stream');
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
    public function testServerOnConnectInvalidAppError()
    {
        $this->setServer(true);
        Server::setServer($this->getServer());

        // 无效的header
        $mockConnection = new MockTcpConnection();
        $this->getServer()->onConnect($mockConnection);
        $this->assertTrue(
            ($onWebSocketConnect = $this->getServer()->_getConnectionProperty($mockConnection, 'onWebSocketConnect')) instanceof Closure
        );

        $onWebSocketConnect($mockConnection, '');
        $this->assertEquals(0, $this->getServer()->_getConnectionProperty($mockConnection, 'clientNotSendPingCount'));
        $this->assertTrue($mockConnection->isPaused());
        $this->assertEquals('{"event":"pusher:error","data":{"code":null,"message":"Invalid app"}}', $mockConnection->getSendBuffer());
        $storage = HookServer::getStorage();
        // 队列新增一条数据
        $this->assertEquals(1, $storage->exists('workbunny:webman-push-server:webhook-stream'));
        // 队列包含一条server_event事件
        $this->assertContains('server_event', array_column($storage->xRead([
            'workbunny:webman-push-server:webhook-stream' => '0-0'
        ], -1, 1)['workbunny:webman-push-server:webhook-stream'] ?? [], 'name'));
        // 移除队列
        $storage->del('workbunny:webman-push-server:webhook-stream');
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
    public function testServerOnConnectInvalidAppKeyError()
    {
        $this->setServer(true);
        Server::setServer($this->getServer());

        // 无效的akk_key
        $mockConnection = new MockTcpConnection();
        $this->getServer()->onConnect($mockConnection);
        $this->assertTrue(
            ($onWebSocketConnect = $this->getServer()->_getConnectionProperty($mockConnection, 'onWebSocketConnect')) instanceof Closure
        );

        $onWebSocketConnect($mockConnection, "GET /app/none?protocol=7&client=js&version=3.2.4&flash=false HTTP/1.1\r\nConnection: Upgrade\r\nUpgrade: websocket\r\n\r\n");
        $this->assertEquals(0, $this->getServer()->_getConnectionProperty($mockConnection, 'clientNotSendPingCount'));
        $this->assertTrue($mockConnection->isPaused());
        $this->assertEquals('{"event":"pusher:error","data":{"code":null,"message":"Invalid app_key"}}', $mockConnection->getSendBuffer());
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
        Server::setServer($this->getServer());

        // bool
        $mockConnection = new MockTcpConnection();
        $this->getServer()->onMessage($mockConnection, true);

        $this->assertFalse(isset($mockConnection->clientNotSendPingCount));
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
        Server::setServer($this->getServer());

        // array
        $mockConnection = new MockTcpConnection();
        $this->getServer()->onMessage($mockConnection, []);

        $this->assertFalse(isset($mockConnection->clientNotSendPingCount));
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
        Server::setServer($this->getServer());

        // object
        $mockConnection = new MockTcpConnection();
        $this->getServer()->onMessage($mockConnection, $mockConnection);

        $this->assertFalse(isset($mockConnection->clientNotSendPingCount));
        $this->assertEquals(null, $this->getServer()->_getConnectionProperty($mockConnection, 'clientNotSendPingCount'));
    }
}
