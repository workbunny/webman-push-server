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
use Workbunny\WebmanPushServer\Events\ClientEvent;
use Workbunny\WebmanPushServer\Events\Ping;
use Workbunny\WebmanPushServer\Events\Subscribe;
use Workbunny\WebmanPushServer\Events\Unsubscribe;
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
        $onWebSocketConnect = $this->getServer()->_getConnectionProperty($mockConnection, 'onWebSocketConnect');
        // 手动触发 onWebSocketConnect 回调
        $onWebSocketConnect($mockConnection, "/app/{$this->_auth_key}?{$this->_query_string}");
        // 手动触发 onMessage 回调
        $this->getServer()->onMessage($mockConnection, json_encode([
            'event' => EVENT_PING
        ], JSON_UNESCAPED_UNICODE));

        $this->assertEquals(true, isset($mockConnection->clientNotSendPingCount));
        $this->assertEquals(0, $this->getServer()->_getConnectionProperty($mockConnection, 'clientNotSendPingCount'));
        $this->assertEquals(true, Server::$eventFactory instanceof Ping);
        $this->assertEquals('{"event":"pusher:pong","data":{}}', $mockConnection->getSendBuffer());
    }

    /**
     * 测试消息事件处理
     * @covers \Workbunny\WebmanPushServer\Server::onMessage
     * @covers \Workbunny\WebmanPushServer\Server::_setConnectionProperty
     * @covers \Workbunny\WebmanPushServer\Server::_getConnectionProperty
     * @throws Exception
     */
    public function testServerEventHandlerBySubscribe(){
        $this->setServer(true);

        $mockConnection = new MockTcpConnection();
        // 手动触发 onConnect 回调
        $this->getServer()->onConnect($mockConnection);
        $onWebSocketConnect = $this->getServer()->_getConnectionProperty($mockConnection, 'onWebSocketConnect');
        // 手动触发 onWebSocketConnect 回调
        $onWebSocketConnect($mockConnection, "/app/{$this->_auth_key}?{$this->_query_string}");
        // 手动触发 onMessage 回调
        $this->getServer()->onMessage($mockConnection, json_encode([
            'event' => EVENT_SUBSCRIBE,
            'data' => [
                'channel' => 'public-abc'
            ]
        ], JSON_UNESCAPED_UNICODE));

        $this->assertEquals(true, isset($mockConnection->clientNotSendPingCount));
        $this->assertEquals(0, $this->getServer()->_getConnectionProperty($mockConnection, 'clientNotSendPingCount'));
        $this->assertEquals(true, Server::$eventFactory instanceof Subscribe);
    }

    /**
     * 测试消息事件处理
     * @covers \Workbunny\WebmanPushServer\Server::onMessage
     * @covers \Workbunny\WebmanPushServer\Server::_setConnectionProperty
     * @covers \Workbunny\WebmanPushServer\Server::_getConnectionProperty
     * @throws Exception
     */
    public function testServerEventHandlerByUnsubscribe(){
        $this->setServer(true);

        $mockConnection = new MockTcpConnection();
        // 手动触发 onConnect 回调
        $this->getServer()->onConnect($mockConnection);
        $onWebSocketConnect = $this->getServer()->_getConnectionProperty($mockConnection, 'onWebSocketConnect');
        // 手动触发 onWebSocketConnect 回调
        $onWebSocketConnect($mockConnection, "/app/{$this->_auth_key}?{$this->_query_string}");
        // 手动触发 onMessage 回调
        $this->getServer()->onMessage($mockConnection, json_encode([
            'event' => EVENT_UNSUBSCRIBE,
            'data' => [
                'channel' => 'public-abc'
            ]
        ], JSON_UNESCAPED_UNICODE));

        $this->assertEquals(true, isset($mockConnection->clientNotSendPingCount));
        $this->assertEquals(0, $this->getServer()->_getConnectionProperty($mockConnection, 'clientNotSendPingCount'));
        $this->assertEquals(true, Server::$eventFactory instanceof Unsubscribe);
    }

    /**
     * 测试消息事件处理
     * @covers \Workbunny\WebmanPushServer\Server::onMessage
     * @covers \Workbunny\WebmanPushServer\Server::_setConnectionProperty
     * @covers \Workbunny\WebmanPushServer\Server::_getConnectionProperty
     * @throws Exception
     */
    public function testServerEventHandlerByCustomClientEvent(){
        $this->setServer(true);

        $mockConnection = new MockTcpConnection();
        // 手动触发 onConnect 回调
        $this->getServer()->onConnect($mockConnection);
        $onWebSocketConnect = $this->getServer()->_getConnectionProperty($mockConnection, 'onWebSocketConnect');
        // 手动触发 onWebSocketConnect 回调
        $onWebSocketConnect($mockConnection, "/app/{$this->_auth_key}?{$this->_query_string}");
        // 手动触发 onMessage 回调
        $this->getServer()->onMessage($mockConnection, json_encode([
            'event' => 'pusher:client-test',
            'channel' => 'public-abc',
//            'data' => [
//                'channel' => 'public-abc'
//            ]
        ], JSON_UNESCAPED_UNICODE));

        $this->assertEquals(true, isset($mockConnection->clientNotSendPingCount));
        $this->assertEquals(0, $this->getServer()->_getConnectionProperty($mockConnection, 'clientNotSendPingCount'));
        $this->assertEquals(true, Server::$eventFactory instanceof ClientEvent);
        $this->assertEquals(
            '{"event":"pusher:error","data":{"code":null,"message":"Client event rejected - only supported on private and presence channels"}}',
            $mockConnection->getSendBuffer()
        );
    }

    /**
     * 测试消息事件处理
     * @covers \Workbunny\WebmanPushServer\Server::onMessage
     * @covers \Workbunny\WebmanPushServer\Server::_setConnectionProperty
     * @covers \Workbunny\WebmanPushServer\Server::_getConnectionProperty
     * @throws Exception
     */
    public function testServerEventHandlerByServerReturnClientEvent(){
        $this->setServer(true);

        // server return client-event
        $mockConnection = new MockTcpConnection();
        // 手动触发 onConnect 回调
        $this->getServer()->onConnect($mockConnection);
        $onWebSocketConnect = $this->getServer()->_getConnectionProperty($mockConnection, 'onWebSocketConnect');
        // 手动触发 onWebSocketConnect 回调
        $onWebSocketConnect($mockConnection, "/app/{$this->_auth_key}?{$this->_query_string}");
        // 手动触发 onMessage 回调
        $this->getServer()->onMessage($mockConnection, json_encode([
            'event' => EVENT_PONG,
        ], JSON_UNESCAPED_UNICODE));

        $this->assertEquals(true, isset($mockConnection->clientNotSendPingCount));
        $this->assertEquals(0, $this->getServer()->_getConnectionProperty($mockConnection, 'clientNotSendPingCount'));
        $this->assertEquals(true, Server::$eventFactory instanceof ClientEvent);
        $this->assertEquals('{"event":"pusher:error","data":{"code":null,"message":"Bad channel"}}', $mockConnection->getSendBuffer());

        // internal Server-event
        $mockConnection = new MockTcpConnection();
        // 手动触发 onConnect 回调
        $this->getServer()->onConnect($mockConnection);
        $onWebSocketConnect = $this->getServer()->_getConnectionProperty($mockConnection, 'onWebSocketConnect');
        // 手动触发 onWebSocketConnect 回调
        $onWebSocketConnect($mockConnection, "/app/{$this->_auth_key}?{$this->_query_string}");
        // 手动触发 onMessage 回调
        $this->getServer()->onMessage($mockConnection, json_encode([
            'event' => EVENT_MEMBER_REMOVED
        ], JSON_UNESCAPED_UNICODE));

        $this->assertEquals(true, isset($mockConnection->clientNotSendPingCount));
        $this->assertEquals(0, $this->getServer()->_getConnectionProperty($mockConnection, 'clientNotSendPingCount'));
        $this->assertEquals(null, Server::$eventFactory);
        $this->assertEquals('{"event":"pusher:error","data":{"code":null,"message":"Client event rejected - Unknown event"}}', $mockConnection->getSendBuffer());
    }
}
