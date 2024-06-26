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

namespace Workbunny\WebmanPushServer\Events;

use Workerman\Connection\TcpConnection;

class ServerEvent extends AbstractEvent
{
    /**
     * server事件无需响应
     *
     * @param TcpConnection $connection
     * @param array $request
     * @return void
     */
    public function response(TcpConnection $connection, array $request): void
    {
        return;
    }
}