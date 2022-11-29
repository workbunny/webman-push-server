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
}
