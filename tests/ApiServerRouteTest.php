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
use Webman\Http\Request;
use Webman\Http\Response;
use Workbunny\WebmanPushServer\ApiClient;

class ApiServerRouteTest extends BaseTestCase
{

    public function testApiServerRouteChannels(){
        // required auth_key
        $mockConnection = new MockTcpConnection();
        $request = new Request("GET /apps/1/channels HTTP/1.1\r\nConnection: keep-alive\r\n\r\n");
        // 手动触发 onMessage 回调
        $this->getApiServer()->onMessage($mockConnection, $request);
        $this->assertTrue($mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('{"error":"Required auth_key"}', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(400, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));

        // invalid auth_key
        $mockConnection = new MockTcpConnection();
        $request = new Request("GET /apps/1/channels?auth_key=test HTTP/1.1\r\nConnection: keep-alive\r\n\r\n");
        // 手动触发 onMessage 回调
        $this->getApiServer()->onMessage($mockConnection, $request);
        $this->assertTrue($mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('{"error":"Invalid auth_key"}', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(401, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));

        // invalid auth_key
        $mockConnection = new MockTcpConnection();
        $request = new Request("GET /apps/1/channels?auth_key=workbunny&auth_signature=abc HTTP/1.1\r\nConnection: keep-alive\r\n\r\n");
        // 手动触发 onMessage 回调
        $this->getApiServer()->onMessage($mockConnection, $request);
        $this->assertTrue($mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('{"error":"Invalid signature"}', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(401, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));

        // successful
        $mockConnection = new MockTcpConnection();
        $signature = ApiClient::routeAuth('workbunny', 'U2FsdGVkX1+vlfFH8Q9XdZ9t9h2bABGYAZltEYAX6UM=', 'GET', '/apps/1/channels', [
            'auth_key' => 'workbunny'
        ]);
        $request = new Request("GET /apps/1/channels?auth_key=workbunny&auth_signature=$signature HTTP/1.1\r\nConnection: keep-alive\r\n\r\n");
        // 手动触发 onMessage 回调
        $this->getApiServer()->onMessage($mockConnection, $request);
        $this->assertTrue($mockConnection->getSendBuffer() instanceof Response);
        $this->assertTrue(isset(json_decode($mockConnection->getSendBuffer()->rawBody(),true)['channels']));
        $this->assertEquals(200, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));
    }


    public function testApiServerRouteChannel(){
        $mockConnection = new MockTcpConnection();
        $signature = ApiClient::routeAuth('workbunny', 'U2FsdGVkX1+vlfFH8Q9XdZ9t9h2bABGYAZltEYAX6UM=', 'GET', '/apps/1/channels/private-test', [
            'auth_key' => 'workbunny'
        ]);
        $request = new Request("GET /apps/1/channels/private-test?auth_key=workbunny&auth_signature=$signature HTTP/1.1\r\nConnection: keep-alive\r\n\r\n");
        // 手动触发 onMessage 回调
        $this->getApiServer()->onMessage($mockConnection, $request);
        $this->assertTrue($mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('{"occupied":true,"type":false}', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(200, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));

        $mockConnection = new MockTcpConnection();
        $signature = ApiClient::routeAuth('workbunny', 'U2FsdGVkX1+vlfFH8Q9XdZ9t9h2bABGYAZltEYAX6UM=', 'GET', '/apps/1/channels/private-test', [
            'auth_key'  => 'workbunny',
            'info'      => 'subscription_count'
        ]);
        $request = new Request("GET /apps/1/channels/private-test?auth_key=workbunny&auth_signature=$signature&info=subscription_count HTTP/1.1\r\nConnection: keep-alive\r\n\r\n");
        // 手动触发 onMessage 回调
        $this->getApiServer()->onMessage($mockConnection, $request);
        $this->assertTrue($mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('{"occupied":true,"type":false,"subscription_count":false}', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(200, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));

        $mockConnection = new MockTcpConnection();
        $signature = ApiClient::routeAuth('workbunny', 'U2FsdGVkX1+vlfFH8Q9XdZ9t9h2bABGYAZltEYAX6UM=', 'GET', '/apps/1/channels/private-test', [
            'auth_key'  => 'workbunny',
            'info'      => 'subscription_count,user_count'
        ]);
        $request = new Request("GET /apps/1/channels/private-test?auth_key=workbunny&auth_signature=$signature&info=subscription_count,user_count HTTP/1.1\r\nConnection: keep-alive\r\n\r\n");
        // 手动触发 onMessage 回调
        $this->getApiServer()->onMessage($mockConnection, $request);
        $this->assertTrue($mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('{"occupied":true,"type":false,"subscription_count":false,"user_count":false}', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(200, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));
    }

    public function testApiServerRouteEvents(){
        $mockConnection = new MockTcpConnection();
        $signature = ApiClient::routeAuth('workbunny', 'U2FsdGVkX1+vlfFH8Q9XdZ9t9h2bABGYAZltEYAX6UM=', 'POST', '/apps/1/events', [
            'auth_key' => 'workbunny'
        ]);
        $request = new Request("POST /apps/1/events?auth_key=workbunny&auth_signature=$signature HTTP/1.1\r\nConnection: keep-alive\r\n\r\n");

        // 手动触发 onMessage 回调
        $this->getApiServer()->onMessage($mockConnection, $request);

        $this->assertTrue($mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('{"error":"Required name"}', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(400, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));
    }

    public function testApiServerRouteBatchEvents(){

        $mockConnection = new MockTcpConnection();
        $signature = ApiClient::routeAuth('workbunny', 'U2FsdGVkX1+vlfFH8Q9XdZ9t9h2bABGYAZltEYAX6UM=', 'POST', '/apps/1/batch_events', [
            'auth_key' => 'workbunny'
        ]);
        $request = new Request("POST /apps/1/batch_events?auth_key=workbunny&auth_signature=$signature HTTP/1.1\r\nConnection: keep-alive\r\n\r\n");

        // 手动触发 onMessage 回调
        $this->getApiServer()->onMessage($mockConnection, $request);

        $this->assertTrue($mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('{"error":"Required batch"}', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(400, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));
    }

    public function testApiServerRouteUsers(){

        $mockConnection = new MockTcpConnection();
        $signature = ApiClient::routeAuth('workbunny', 'U2FsdGVkX1+vlfFH8Q9XdZ9t9h2bABGYAZltEYAX6UM=', 'GET', '/apps/1/channels/private-test/users', [
            'auth_key' => 'workbunny'
        ]);
        $request = new Request("GET /apps/1/channels/private-test/users?auth_key=workbunny&auth_signature=$signature HTTP/1.1\r\nConnection: keep-alive\r\n\r\n");
        // 手动触发 onMessage 回调
        $this->getApiServer()->onMessage($mockConnection, $request);
        $this->assertTrue($mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('{"error":"Not Found [private-test]"}', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(404, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));
    }

    public function testApiServerRouteTerminateConnections(){

        $mockConnection = new MockTcpConnection();
        $signature = ApiClient::routeAuth('workbunny', 'U2FsdGVkX1+vlfFH8Q9XdZ9t9h2bABGYAZltEYAX6UM=', 'POST', '/apps/1/users/abc/terminate_connections', [
            'auth_key' => 'workbunny'
        ]);
        $request = new Request("POST /apps/1/users/abc/terminate_connections?auth_key=workbunny&auth_signature=$signature HTTP/1.1\r\nConnection: keep-alive\r\n\r\n");

        // 手动触发 onMessage 回调
        $this->getApiServer()->onMessage($mockConnection, $request);

        $this->assertTrue($mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('{}', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(200, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));
    }
}
