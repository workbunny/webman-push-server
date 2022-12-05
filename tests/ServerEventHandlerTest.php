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
use Workbunny\WebmanPushServer\Events\ClientEvent;
use Workbunny\WebmanPushServer\Events\Ping;
use Workbunny\WebmanPushServer\Events\ServerEvent;
use Workbunny\WebmanPushServer\Events\Subscribe;
use Workbunny\WebmanPushServer\Events\Unsubscribe;
use Workbunny\WebmanPushServer\HookServer;
use Workbunny\WebmanPushServer\Server;
use const Workbunny\WebmanPushServer\EVENT_MEMBER_REMOVED;
use const Workbunny\WebmanPushServer\EVENT_PING;
use const Workbunny\WebmanPushServer\EVENT_PONG;
use const Workbunny\WebmanPushServer\EVENT_SUBSCRIBE;
use const Workbunny\WebmanPushServer\EVENT_UNSUBSCRIBE;


class ServerEventHandlerTest extends BaseTest
{
    /**
     * 测试消息事件处理
     * @covers \Workbunny\WebmanPushServer\Server::onMessage
     * @covers \Workbunny\WebmanPushServer\Server::_setConnectionProperty
     * @covers \Workbunny\WebmanPushServer\Server::_getConnectionProperty
     * @throws Exception
     */
    public function testServerEventHandlerByPing(){
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

//

//
//        $this->getServer()->onMessage($mockConnection, json_encode([
//            'event' => EVENT_PING
//        ], JSON_UNESCAPED_UNICODE));
//
//        $this->assertEquals(true, isset($mockConnection->clientNotSendPingCount));
//        $this->assertEquals(0, $this->getServer()->_getConnectionProperty($mockConnection, 'clientNotSendPingCount'));
//        $this->assertEquals(true, Server::$eventFactory instanceof Ping);
    }

//    /**
//     * 测试消息事件处理
//     * @covers \Workbunny\WebmanPushServer\Server::onMessage
//     * @covers \Workbunny\WebmanPushServer\Server::_setConnectionProperty
//     * @covers \Workbunny\WebmanPushServer\Server::_getConnectionProperty
//     * @throws Exception
//     */
//    public function testServerEventHandlerBySubscribe(){
//        $this->setServer(true);
//
//        // subscribe
//        $mockConnection = new MockTcpConnection();
//
//        $this->getServer()->onConnect($mockConnection);
//        $this->assertEquals(false, $mockConnection->isPaused());
//
//        $this->getServer()->onMessage($mockConnection, json_encode([
//            'event' => EVENT_SUBSCRIBE
//        ], JSON_UNESCAPED_UNICODE));
//
//        $this->assertEquals(true, isset($mockConnection->clientNotSendPingCount));
//        $this->assertEquals(0, $this->getServer()->_getConnectionProperty($mockConnection, 'clientNotSendPingCount'));
//        $this->assertEquals(true, Server::$eventFactory instanceof Subscribe);
//    }
//
//    /**
//     * 测试消息事件处理
//     * @covers \Workbunny\WebmanPushServer\Server::onMessage
//     * @covers \Workbunny\WebmanPushServer\Server::_setConnectionProperty
//     * @covers \Workbunny\WebmanPushServer\Server::_getConnectionProperty
//     * @throws Exception
//     */
//    public function testServerEventHandlerByUnsubscribe(){
//        $this->setServer(true);
//
//        // subscribe
//        $mockConnection = new MockTcpConnection();
//
//        $this->getServer()->onConnect($mockConnection);
//        $this->assertEquals(false, $mockConnection->isPaused());
//
//        $this->getServer()->onMessage($mockConnection, json_encode([
//            'event' => EVENT_UNSUBSCRIBE
//        ], JSON_UNESCAPED_UNICODE));
//
//        $this->assertEquals(true, isset($mockConnection->clientNotSendPingCount));
//        $this->assertEquals(0, $this->getServer()->_getConnectionProperty($mockConnection, 'clientNotSendPingCount'));
//        $this->assertEquals(true, Server::$eventFactory instanceof Unsubscribe);
//    }
//
//    /**
//     * 测试消息事件处理
//     * @covers \Workbunny\WebmanPushServer\Server::onMessage
//     * @covers \Workbunny\WebmanPushServer\Server::_setConnectionProperty
//     * @covers \Workbunny\WebmanPushServer\Server::_getConnectionProperty
//     * @throws Exception
//     */
//    public function testServerEventHandlerByCustomClientEvent(){
//        $this->setServer(true);
//
//        // Custom Client-event
//        $mockConnection = new MockTcpConnection();
//
//        $this->getServer()->onConnect($mockConnection);
//        $this->assertEquals(false, $mockConnection->isPaused());
//
//        $this->getServer()->onMessage($mockConnection, json_encode([
//            'event' => 'pusher:client-test'
//        ], JSON_UNESCAPED_UNICODE));
//
//        $this->assertEquals(true, isset($mockConnection->clientNotSendPingCount));
//        $this->assertEquals(0, $this->getServer()->_getConnectionProperty($mockConnection, 'clientNotSendPingCount'));
//        $this->assertEquals(true, Server::$eventFactory instanceof ClientEvent);
//    }
//
//    /**
//     * 测试消息事件处理
//     * @covers \Workbunny\WebmanPushServer\Server::onMessage
//     * @covers \Workbunny\WebmanPushServer\Server::_setConnectionProperty
//     * @covers \Workbunny\WebmanPushServer\Server::_getConnectionProperty
//     * @throws Exception
//     */
//    public function testServerEventHandlerByServerReturnClientEvent(){
//        $this->setServer(true);
//
//        // server return client-event
//        $mockConnection = new MockTcpConnection();
//
//        $this->getServer()->onConnect($mockConnection);
//        $this->assertEquals(false, $mockConnection->isPaused());
//
//        $this->getServer()->onMessage($mockConnection, json_encode([
//            'event' => EVENT_PONG
//        ], JSON_UNESCAPED_UNICODE));
//
//        $this->assertEquals(true, isset($mockConnection->clientNotSendPingCount));
//        $this->assertEquals(0, $this->getServer()->_getConnectionProperty($mockConnection, 'clientNotSendPingCount'));
//        $this->assertEquals(true, Server::$eventFactory instanceof ClientEvent);
//
//        // internal Server-event
//        $mockConnection = new MockTcpConnection();
//        $this->getServer()->onMessage($mockConnection, json_encode([
//            'event' => EVENT_MEMBER_REMOVED
//        ], JSON_UNESCAPED_UNICODE));
//
//        $this->assertEquals(true, isset($mockConnection->clientNotSendPingCount));
//        $this->assertEquals(0, $this->getServer()->_getConnectionProperty($mockConnection, 'clientNotSendPingCount'));
//        $this->assertEquals(true, Server::$eventFactory instanceof ServerEvent);
//    }
//
//    /**
//     * 测试消息事件处理
//     * @covers \Workbunny\WebmanPushServer\Server::onMessage
//     * @covers \Workbunny\WebmanPushServer\Server::_setConnectionProperty
//     * @covers \Workbunny\WebmanPushServer\Server::_getConnectionProperty
//     * @throws Exception
//     */
//    public function testServerEventHandlerByInternalServerEvent(){
//        $this->setServer(true);
//
//        // internal Server-event
//        $mockConnection = new MockTcpConnection();
//
//        $this->getServer()->onConnect($mockConnection);
//        $this->assertEquals(false, $mockConnection->isPaused());
//
//        $this->getServer()->onMessage($mockConnection, json_encode([
//            'event' => EVENT_MEMBER_REMOVED
//        ], JSON_UNESCAPED_UNICODE));
//
//        $this->assertEquals(true, isset($mockConnection->clientNotSendPingCount));
//        $this->assertEquals(0, $this->getServer()->_getConnectionProperty($mockConnection, 'clientNotSendPingCount'));
//        $this->assertEquals(true, Server::$eventFactory instanceof ServerEvent);
//    }
}
