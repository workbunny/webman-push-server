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
use Workbunny\WebmanPushServer\ApiRoute;
use Workbunny\WebmanPushServer\ApiService;
use Workerman\Worker;

/**
 * TODO
 */
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
