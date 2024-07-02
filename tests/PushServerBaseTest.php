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
use Workbunny\WebmanPushServer\PushServer;

class PushServerBaseTest extends BaseTestCase
{
    /**
     * 测试server初始化
     * @throws Exception
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
    }


}
