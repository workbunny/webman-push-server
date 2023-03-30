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
use Workbunny\WebmanPushServer\Server;
use Workerman\Protocols\Http;
use Workerman\Protocols\Http\Response;

class ApiServiceRouteHandlerTest extends BaseTestCase
{
    /**
     * 测试消息事件处理
     * @covers \Workbunny\WebmanPushServer\ApiService::onMessage
     * @covers \Workbunny\WebmanPushServer\Server::__construct
     * @throws Exception
     */
    public function testApiServiceChannels(){
        $this->setServer(true);

        Server::setServer($this->getServer());

        // required auth_key
        $mockConnection = new MockTcpConnection();
        $request = new Http\Request("GET /apps/1/channels HTTP/1.1\r\nConnection: keep-alive\r\n");
        // 手动触发 onMessage 回调
        Server::getServices(ApiService::class)->onMessage($mockConnection, $request);
        $this->assertEquals(true, $mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('{"error":"Required auth_key"}', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(400, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));

        // invalid auth_key
        $mockConnection = new MockTcpConnection();
        $request = new Http\Request("GET /apps/1/channels?auth_key=test HTTP/1.1\r\nConnection: keep-alive\r\n");
        // 手动触发 onMessage 回调
        Server::getServices(ApiService::class)->onMessage($mockConnection, $request);
        $this->assertEquals(true, $mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('{"error":"Invalid auth_key"}', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(401, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));

        // invalid auth_key
        $mockConnection = new MockTcpConnection();
        $request = new Http\Request("GET /apps/1/channels?auth_key=workbunny&auth_signature=abc HTTP/1.1\r\nConnection: keep-alive\r\n");
        // 手动触发 onMessage 回调
        Server::getServices(ApiService::class)->onMessage($mockConnection, $request);
        $this->assertEquals(true, $mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('{"error":"Invalid signature"}', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(401, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));

        // successful
        $mockConnection = new MockTcpConnection();
        $request = new Http\Request("GET /apps/1/channels?auth_key=workbunny&auth_signature=test HTTP/1.1\r\nConnection: keep-alive\r\n");
        // 手动触发 onMessage 回调
        Server::getServices(ApiService::class)->onMessage($mockConnection, $request);
        $this->assertEquals(true, $mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('{"channels":[]}', $mockConnection->getSendBuffer()->rawBody());
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
        $request = new Http\Request("GET /apps/1/channels/private-test?auth_key=workbunny&auth_signature=test HTTP/1.1\r\nConnection: keep-alive\r\n");
        // 手动触发 onMessage 回调
        Server::getServices(ApiService::class)->onMessage($mockConnection, $request);
        $this->assertEquals(true, $mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('{"occupied":true,"type":null}', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(200, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));

        $mockConnection = new MockTcpConnection();
        $request = new Http\Request("GET /apps/1/channels/private-test?auth_key=workbunny&auth_signature=test&info=subscription_count HTTP/1.1\r\nConnection: keep-alive\r\n");
        // 手动触发 onMessage 回调
        Server::getServices(ApiService::class)->onMessage($mockConnection, $request);
        $this->assertEquals(true, $mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('{"occupied":true,"type":null,"subscription_count":null}', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(200, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));

        $mockConnection = new MockTcpConnection();
        $request = new Http\Request("GET /apps/1/channels/private-test?auth_key=workbunny&auth_signature=test&info=subscription_count,user_count HTTP/1.1\r\nConnection: keep-alive\r\n");
        // 手动触发 onMessage 回调
        Server::getServices(ApiService::class)->onMessage($mockConnection, $request);
        $this->assertEquals(true, $mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('{"occupied":true,"type":null,"subscription_count":null,"user_count":null}', $mockConnection->getSendBuffer()->rawBody());
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
        $request = new Http\Request("POST /apps/1/events?auth_key=workbunny&auth_signature=test HTTP/1.1\r\nConnection: keep-alive\r\n");

        // 手动触发 onMessage 回调
        Server::getServices(ApiService::class)->onMessage($mockConnection, $request);

        $this->assertEquals(true, $mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('{"error":"Required name"}', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(400, $mockConnection->getSendBuffer()->getStatusCode());
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
        $request = new Http\Request("POST /apps/1/batch_events?auth_key=workbunny&auth_signature=test HTTP/1.1\r\nConnection: keep-alive\r\n");

        // 手动触发 onMessage 回调
        Server::getServices(ApiService::class)->onMessage($mockConnection, $request);

        $this->assertEquals(true, $mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('{"error":"Required batch"}', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(400, $mockConnection->getSendBuffer()->getStatusCode());
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
        $request = new Http\Request("GET /apps/1/channels/private-test/users?auth_key=workbunny&auth_signature=test HTTP/1.1\r\nConnection: keep-alive\r\n");
        // 手动触发 onMessage 回调
        Server::getServices(ApiService::class)->onMessage($mockConnection, $request);
        $this->assertEquals(true, $mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('{"error":"Not Found [private-test]"}', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(404, $mockConnection->getSendBuffer()->getStatusCode());
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
        $request = new Http\Request("POST /apps/1/users/abc/terminate_connections?auth_key=workbunny&auth_signature=test HTTP/1.1\r\nConnection: keep-alive\r\n");

        // 手动触发 onMessage 回调
        Server::getServices(ApiService::class)->onMessage($mockConnection, $request);

        $this->assertEquals(true, $mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('{}', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(200, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));
    }
}
