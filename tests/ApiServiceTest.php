<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Workbunny\WebmanPushServer\ApiRoute;
use Workbunny\WebmanPushServer\ApiService;
use Workerman\Worker;

class ApiServiceTest extends TestCase
{
    /**
     * @var ApiService|null
     */
    protected ?ApiService $service = null;

    /**
     * @var Worker|null
     */
    protected ?Worker $worker = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->worker = new Worker();
        $this->service = new ApiService();
        ApiRoute::start($this->worker);
        parent::setUp();
    }

    public function testAuthLogin()
    {
        $this->client()::$mockHandler = new MockHandler([new Response()]);
        $this->client()->auth->login('test', '123123');

        $request = $this->client()::$mockHandler->getLastRequest();

        $this->assertEquals(
            '/nacos/v1/auth/users/login',
            $request->getUri()->getPath()
        );
        $this->assertEquals(
            '127.0.0.1',
            $request->getUri()->getHost()
        );
        $this->assertEquals(
            8848,
            $request->getUri()->getPort()
        );
        $this->assertEquals(
            'username=test',
            $request->getUri()->getQuery()
        );
        $this->assertEquals(
            'password=123123',
            $request->getBody()->getContents()
        );
        $this->assertEquals(
            'POST',
            $request->getMethod()
        );
    }
}
