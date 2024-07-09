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

class ClientType extends AbstractPublishType
{

    /** @inheritDoc */
    public static function response(array $data): void
    {
        static::verify($data, [
            ['appKey', 'is_string', true],
            ['channel', 'is_string', true],
            ['event', 'is_string', true],
            ['socketId', 'is_string', false]
        ]);
        // 查询通道下的所有socketId
        $socketIds = PushServer::getChannels($appKey = $data['appKey'], $data['channel']);
        // 发送至socketId对应的连接
        foreach ($socketIds as $socketId) {
            // 如果存在socketId字段，则是需要做忽略发送
            if ($socketId !== ($data['socketId'] ?? null)) {
                // 获取对应connection对象
                if ($connection = PushServer::getConnection($appKey, $socketId)) {
                    // 发送
                    PushServer::send(
                        $connection,
                        $data['channel'],
                        $data['event'],
                        $data['data'] ?? '{}'
                    );
                }
            }
        }
    }
}