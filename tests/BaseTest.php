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
use PHPUnit\Framework\TestCase;
use Workbunny\WebmanPushServer\ApiService;
use Workbunny\WebmanPushServer\Server;


abstract class BaseTest extends TestCase
{
    protected string $_auth_key = 'workbunny';

    protected string $_query_string = 'protocol=7&client=js&version=3.2.4&flash=false';

    /**
     * @var array[]
     */
    protected array $_services = [
        ApiService::class => [
            'handler'     => ApiService::class,
            'listen'      => 'http://0.0.0.0:8002',
            'context'     => [],
            'constructor' => []
        ]
    ];

    /**
     * @var Server|null
     */
    protected ?Server $_server = null;

    /**
     * @return Server|null
     */
    protected function getServer(): ?Server
    {
        return $this->_server;
    }

    /**
     * @param bool $clean
     * @return void
     * @throws Exception
     */
    protected function setServer(bool $clean = false): void
    {
        $this->_server = new Server($this->_services);
        if($clean){
            ob_clean();
        }
    }

    protected function setUp(): void
    {
        Server::$debug = true;
        require_once dirname(__DIR__) . '/vendor/workerman/webman-framework/src/support/helpers.php';
        parent::setUp();
    }
}
