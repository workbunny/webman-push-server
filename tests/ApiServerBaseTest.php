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

use support\Redis;
use Tests\MockClass\MockTcpConnection;
use Webman\Http\Request;
use Webman\Http\Response;
use Workbunny\WebmanPushServer\ApiRoute;
use Workerman\Worker;

class ApiServerBaseTest extends BaseTestCase
{
    public function testApiServerOnMessageSuccessful()
    {
        $mockConnection = new MockTcpConnection();
        $request = new Request("GET /index HTTP/1.1\r\nConnection: keep-alive\r\n\r\n");
        // 手动触发 onMessage 回调
        $this->getApiServer()->onMessage($mockConnection, $request);

        $this->assertTrue($mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('Hello Workbunny!', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(200, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));
    }


    public function testApiServerOnMessageWithNormalStringData()
    {
        $mockConnection = new MockTcpConnection();

        // 手动触发 onMessage 回调
        $this->getApiServer()->onMessage($mockConnection, '');

        $this->assertTrue($mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('Bad Request.', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(400, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));

        $mockConnection = new MockTcpConnection();

        // 手动触发 onMessage 回调
        $this->getApiServer()->onMessage($mockConnection, 'test');

        $this->assertTrue($mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('Bad Request.', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(400, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));
    }


    public function testApiServerOnMessageWithBoolData()
    {
        $mockConnection = new MockTcpConnection();

        // 手动触发 onMessage 回调
        $this->getApiServer()->onMessage($mockConnection, true);

        $this->assertTrue($mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('Bad Request.', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(400, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));

        $mockConnection = new MockTcpConnection();

        // 手动触发 onMessage 回调
        $this->getApiServer()->onMessage($mockConnection, false);

        $this->assertTrue($mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('Bad Request.', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(400, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));
    }


    public function testApiServerOnMessageWithNumberData()
    {
        $mockConnection = new MockTcpConnection();

        // 手动触发 onMessage 回调
        $this->getApiServer()->onMessage($mockConnection, 1.1);

        $this->assertTrue($mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('Bad Request.', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(400, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));

        // 手动触发 onMessage 回调
        $this->getApiServer()->onMessage($mockConnection, 1);

        $this->assertTrue($mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('Bad Request.', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(400, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));
    }


    public function testApiServerOnMessageWithArrayData()
    {
        $mockConnection = new MockTcpConnection();

        // 手动触发 onMessage 回调
        $this->getApiServer()->onMessage($mockConnection, []);

        $this->assertTrue($mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('Bad Request.', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(400, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));

        // 手动触发 onMessage 回调
        $this->getApiServer()->onMessage($mockConnection, [
            'test'
        ]);

        $this->assertTrue($mockConnection->getSendBuffer() instanceof Response);
        $this->assertEquals('Bad Request.', $mockConnection->getSendBuffer()->rawBody());
        $this->assertEquals(400, $mockConnection->getSendBuffer()->getStatusCode());
        $this->assertEquals('application/json', $mockConnection->getSendBuffer()->getHeader('Content-Type'));
    }

    public function testApiServerRegistrar()
    {
        $client = Redis::connection('plugin.workbunny.webman-push-server.server-registrar')->client();

        $this->assertEquals([], $client->keys("registrar:*"));

        $this->getPushServer()->registrarStart($worker = new Worker());

        $this->assertNotEquals([], $keys = $client->keys("registrar:*"));
        $this->assertEquals([
            'master' => '{}'
        ], $client->hGetAll($key = $keys[0]));

        $this->getPushServer()->registrarStop($worker);

        $this->assertEquals(false, $client->exists($key));
        $this->assertEquals([], $client->keys("registrar:*"));
    }
}
