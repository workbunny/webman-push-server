<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Workbunny\WebmanPushServer\Server;
use Workerman\Connection\TcpConnection;

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
