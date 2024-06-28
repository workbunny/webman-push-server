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
        $connection = new MockTcpConnection();
        $this->assertFalse(property_exists($connection, 'onWebSocketConnect'));
        $this->assertFalse(property_exists($connection, 'appKey'));
        $this->assertFalse(property_exists($connection, 'clientNotSendPingCount'));
        $this->assertFalse(property_exists($connection, 'queryString'));
        $this->assertFalse(property_exists($connection, 'socketId'));
        $this->assertFalse(property_exists($connection, 'channels'));
        // 模拟onConnect
        $this->getPushServer()->onConnect($connection);
        $this->assertEquals(
            PushServer::$unknownTag,
            PushServer::_getConnectionProperty($connection, 'appKey', 'has-not')
        );
        $this->assertEquals(
            0,
            PushServer::_getConnectionProperty($connection, 'clientNotSendPingCount', 'has-not')
        );
        $this->assertNotEquals(
            'has-not',
            PushServer::_getConnectionProperty($connection, 'socketId', 'has-not')
        );
        $this->assertFalse(property_exists($connection, 'queryString'));
        $this->assertFalse(property_exists($connection, 'channels'));
        $this->assertTrue(property_exists($connection, 'onWebSocketConnect'));
        // 模拟调用$connection->onWebSocketConnect
        ($connection->onWebSocketConnect)($connection, $this->getWebsocketHeader());
        $this->assertEquals(
            'workbunny',
            PushServer::_getConnectionProperty($connection, 'appKey', 'has-not')
        );
        $this->assertEquals(
            0,
            PushServer::_getConnectionProperty($connection, 'clientNotSendPingCount', 'has-not')
        );
        $this->assertEquals(
            'protocol=7&client=js&version=3.2.4&flash=false',
            PushServer::_getConnectionProperty($connection, 'queryString', 'has-not')
        );
        $this->assertNotEquals(
            'has-not',
            PushServer::_getConnectionProperty($connection, 'socketId', 'has-not')
        );
        $this->assertEquals(
            [],
            PushServer::_getConnectionProperty($connection, 'channels', 'has-not')
        );
    }


}
