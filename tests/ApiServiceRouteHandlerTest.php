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
use Workbunny\WebmanPushServer\ApiService;
use Workbunny\WebmanPushServer\Events\ClientEvent;
use Workbunny\WebmanPushServer\Events\Ping;
use Workbunny\WebmanPushServer\Events\Subscribe;
use Workbunny\WebmanPushServer\Events\Unsubscribe;
use Workbunny\WebmanPushServer\Server;
use Workerman\Protocols\Http;
use Workerman\Protocols\Http\Response;
use const Workbunny\WebmanPushServer\EVENT_MEMBER_REMOVED;
use const Workbunny\WebmanPushServer\EVENT_PING;
use const Workbunny\WebmanPushServer\EVENT_PONG;
use const Workbunny\WebmanPushServer\EVENT_SUBSCRIBE;
use const Workbunny\WebmanPushServer\EVENT_UNSUBSCRIBE;


class ApiServiceRouteHandlerTest extends BaseTest
{
    /**
     * 测试消息事件处理
     * @covers \Workbunny\WebmanPushServer\ApiService::onMessage
     * @covers \Workbunny\WebmanPushServer\Server::__construct
     * @throws Exception
     */
    public function testApiServiceChannels(){
        $this->setServer(true);

        $mockConnection = new MockTcpConnection();
        $request = new Http\Request("GET /index HTTP/1.1\r\nConnection: keep-alive\r\n");

        // 手动触发 onMessage 回调
        Server::getServices(ApiService::class)->onMessage($mockConnection, $request);

        $this->assertEquals(true, $mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('Hello Workbunny!', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(200, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));
    }

    /**
     * 测试消息事件处理
     * @covers \Workbunny\WebmanPushServer\ApiService::onMessage
     * @covers \Workbunny\WebmanPushServer\Server::__construct
     * @throws Exception
     */
    public function testApiServiceChannel(){
        $this->setServer(true);

        $mockConnection = new MockTcpConnection();
        $request = new Http\Request("GET /index HTTP/1.1\r\nConnection: keep-alive\r\n");

        // 手动触发 onMessage 回调
        Server::getServices(ApiService::class)->onMessage($mockConnection, $request);

        $this->assertEquals(true, $mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('Hello Workbunny!', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(200, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));
    }

    /**
     * 测试消息事件处理
     * @covers \Workbunny\WebmanPushServer\ApiService::onMessage
     * @covers \Workbunny\WebmanPushServer\Server::__construct
     * @throws Exception
     */
    public function testApiServiceEvents(){
        $this->setServer(true);

        $mockConnection = new MockTcpConnection();
        $request = new Http\Request("GET /index HTTP/1.1\r\nConnection: keep-alive\r\n");

        // 手动触发 onMessage 回调
        Server::getServices(ApiService::class)->onMessage($mockConnection, $request);

        $this->assertEquals(true, $mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('Hello Workbunny!', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(200, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));
    }

    /**
     * 测试消息事件处理
     * @covers \Workbunny\WebmanPushServer\ApiService::onMessage
     * @covers \Workbunny\WebmanPushServer\Server::__construct
     * @throws Exception
     */
    public function testApiServiceBatchEvents(){
        $this->setServer(true);

        $mockConnection = new MockTcpConnection();
        $request = new Http\Request("GET /index HTTP/1.1\r\nConnection: keep-alive\r\n");

        // 手动触发 onMessage 回调
        Server::getServices(ApiService::class)->onMessage($mockConnection, $request);

        $this->assertEquals(true, $mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('Hello Workbunny!', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(200, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));
    }

    /**
     * 测试消息事件处理
     * @covers \Workbunny\WebmanPushServer\ApiService::onMessage
     * @covers \Workbunny\WebmanPushServer\Server::__construct
     * @throws Exception
     */
    public function testApiServiceUsers(){
        $this->setServer(true);

        $mockConnection = new MockTcpConnection();
        $request = new Http\Request("GET /index HTTP/1.1\r\nConnection: keep-alive\r\n");

        // 手动触发 onMessage 回调
        Server::getServices(ApiService::class)->onMessage($mockConnection, $request);

        $this->assertEquals(true, $mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('Hello Workbunny!', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(200, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));
    }

    /**
     * 测试消息事件处理
     * @covers \Workbunny\WebmanPushServer\ApiService::onMessage
     * @covers \Workbunny\WebmanPushServer\Server::__construct
     * @throws Exception
     */
    public function testApiServiceTerminateConnections(){
        $this->setServer(true);

        $mockConnection = new MockTcpConnection();
        $request = new Http\Request("GET /index HTTP/1.1\r\nConnection: keep-alive\r\n");

        // 手动触发 onMessage 回调
        Server::getServices(ApiService::class)->onMessage($mockConnection, $request);

        $this->assertEquals(true, $mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('Hello Workbunny!', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(200, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));
    }
}
