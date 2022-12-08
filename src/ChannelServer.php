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

namespace Workbunny\WebmanPushServer;

use Workerman\Worker;

class ChannelServer extends \Channel\Server
{
    public function __construct(){}

    public function onWorkerStart(Worker $worker)
    {
        $worker->count = 1;
        $this->_worker = $worker;
        $worker->channels = [];
    }
}