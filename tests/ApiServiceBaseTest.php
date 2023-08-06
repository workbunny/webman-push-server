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

class ApiServiceBaseTest extends BaseTestCase
{
    /**
     * 测试消息事件处理
     * @covers \Workbunny\WebmanPushServer\ApiService::onMessage
     * @covers \Workbunny\WebmanPushServer\Server::__construct
     * @throws Exception
     */
    public function testApiServiceSuccessful(){
        $this->setServer(true);

        $mockConnection = new MockTcpConnection();
        $request = new Http\Request("GET /index HTTP/1.1\r\nConnection: keep-alive\r\n");

        // 手动触发 onMessage 回调
        Server::getServices(ApiService::class)->onMessage($mockConnection, $request);

        $this->assertTrue($mockConnection->getSendBuffer() instanceof Response);
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
    public function testApiServiceWithNormalStringData(){
        $this->setServer(true);

        $mockConnection = new MockTcpConnection();

        // 手动触发 onMessage 回调
        Server::getServices(ApiService::class)->onMessage($mockConnection, '');

        $this->assertTrue($mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('Bad Request.', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(400, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));

        $mockConnection = new MockTcpConnection();

        // 手动触发 onMessage 回调
        Server::getServices(ApiService::class)->onMessage($mockConnection, 'test');

        $this->assertTrue($mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('Bad Request.', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(400, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));
    }

    /**
     * 测试消息事件处理
     * @covers \Workbunny\WebmanPushServer\ApiService::onMessage
     * @covers \Workbunny\WebmanPushServer\Server::__construct
     * @throws Exception
     */
    public function testApiServiceWithBoolData(){
        $this->setServer(true);

        $mockConnection = new MockTcpConnection();

        // 手动触发 onMessage 回调
        Server::getServices(ApiService::class)->onMessage($mockConnection, true);

        $this->assertTrue($mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('Bad Request.', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(400, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));

        $mockConnection = new MockTcpConnection();

        // 手动触发 onMessage 回调
        Server::getServices(ApiService::class)->onMessage($mockConnection, false);

        $this->assertTrue($mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('Bad Request.', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(400, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));
    }

    /**
     * 测试消息事件处理
     * @covers \Workbunny\WebmanPushServer\ApiService::onMessage
     * @covers \Workbunny\WebmanPushServer\Server::__construct
     * @throws Exception
     */
    public function testApiServiceWithNumberData(){
        $this->setServer(true);

        $mockConnection = new MockTcpConnection();

        // 手动触发 onMessage 回调
        Server::getServices(ApiService::class)->onMessage($mockConnection, 1.1);

        $this->assertTrue($mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('Bad Request.', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(400, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));

        // 手动触发 onMessage 回调
        Server::getServices(ApiService::class)->onMessage($mockConnection, 1);

        $this->assertEquals(true, $mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('Bad Request.', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(400, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));
    }

    /**
     * 测试消息事件处理
     * @covers \Workbunny\WebmanPushServer\ApiService::onMessage
     * @covers \Workbunny\WebmanPushServer\Server::__construct
     * @throws Exception
     */
    public function testApiServiceWithArrayData(){
        $this->setServer(true);

        $mockConnection = new MockTcpConnection();

        // 手动触发 onMessage 回调
        Server::getServices(ApiService::class)->onMessage($mockConnection, []);

        $this->assertTrue($mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('Bad Request.', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(400, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));

        // 手动触发 onMessage 回调
        Server::getServices(ApiService::class)->onMessage($mockConnection, [
            'test'
        ]);

        $this->assertTrue($mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('Bad Request.', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(400, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));
    }
}
