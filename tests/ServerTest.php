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

use PHPUnit\Framework\TestCase;
use Workbunny\WebmanPushServer\Server;
use Workerman\Connection\TcpConnection;

/**
 * TODO
 */
class ServerTest extends TestCase
{
    /**
     * @var Server|null
     */
    protected ?Server $server = null;

    protected ?TcpConnection $connection = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->server = new Server();
    }
}
