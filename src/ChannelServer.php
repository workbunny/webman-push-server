<?php declare(strict_types=1);
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

namespace Workbunny\WebmanPushServer;

use Workerman\Worker;

class ChannelServer extends \Channel\Server
{
    public function __construct() {
        // 由于使用了webman自定义进程启动，所以无须Server原有的构造方式
    }

    public function onWorkerStart(Worker $worker) {
        $worker->count     = 1;
        $worker->onMessage = [$this, 'onMessage'];
        $worker->onClose   = [$this, 'onClose'];
        $this->_worker     = $worker;
        $worker->channels  = [];
    }
}