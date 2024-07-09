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

use InvalidArgumentException;
use Workbunny\WebmanPushServer\Events\AbstractEvent;
use Workbunny\WebmanPushServer\Traits\HelperMethods;
use Workerman\Connection\TcpConnection;
use const Workbunny\WebmanPushServer\EVENT_PING;
use const Workbunny\WebmanPushServer\EVENT_SUBSCRIBE;
use const Workbunny\WebmanPushServer\EVENT_UNSUBSCRIBE;
use const Workbunny\WebmanPushServer\PUSH_SERVER_EVENT_CLIENT_EVENT;
use const Workbunny\WebmanPushServer\PUSH_SERVER_EVENT_SERVER_EVENT;

abstract class AbstractPublishType
{
    use HelperMethods;

    const PUBLISH_TYPE_SERVER = 'server';
    const PUBLISH_TYPE_CLIENT = 'client';

    /**
     * @var AbstractPublishType[]|string[]
     */
    protected static array $_publishTypes = [
        self::PUBLISH_TYPE_CLIENT  => ClientType::class,
        self::PUBLISH_TYPE_SERVER  => ServerType::class,
    ];

    /**
     * 工厂
     *
     * @param string $publishType
     * @return AbstractPublishType|null
     */
    public static function factory(string $publishType): ?string
    {
        if (self::exists($publishType)) {
            return self::$_publishTypes[$publishType];
        }
        return null;
    }

    /**
     * 注册publish type响应
     *
     * @param string $publishType
     * @param string $className
     * @param bool $reset
     * @return void
     */
    final public static function register(string $publishType, string $className, bool $reset = false): void
    {
        if (!$reset and self::exists($publishType)) {
            throw new InvalidArgumentException("Event $publishType already exists. ");
        }
        if (!is_a($className, AbstractPublishType::class, true)) {
            throw new InvalidArgumentException("Invalid event class $className. ");
        }
        self::$_publishTypes[$publishType] = $className;
    }

    /**
     * 检测publish type
     *
     * @param string $publishType
     * @return bool
     */
    final public static function exists(string $publishType): bool
    {
        return isset(self::$_publishTypes[$publishType]);
    }

    /**
     * publish type响应
     *
     * @param array $data
     * @return void
     * @throws InvalidArgumentException
     */
    abstract public static function response(array $data): void;
}