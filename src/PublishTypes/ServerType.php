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

namespace Workbunny\WebmanPushServer\PublishTypes;

use Workbunny\WebmanPushServer\PushServer;
use const Workbunny\WebmanPushServer\EVENT_TERMINATE_CONNECTION;

class ServerType extends AbstractPublishType
{

    /** @inheritDoc */
    public static function response(array $data): void
    {
        static::verify($data, [
            ['event', 'is_string', true],
            ['appKey', 'is_string', false],
            ['socketId', 'is_string', false],
        ]);
        // 断开连接事件
        if (
            ($appKey = $data['appKey'] ?? null) and
            ($socketId = $data['socketId'] ?? null) and
            ($data['event'] ?? null) === EVENT_TERMINATE_CONNECTION
        ) {
            PushServer::terminateConnections($appKey, $socketId, $data['data'] ?? []);
        }
        // todo 其他
    }
}