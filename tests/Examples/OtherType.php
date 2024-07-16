<?php declare(strict_types=1);

namespace Tests\Examples;

use Workbunny\WebmanPushServer\PublishTypes\AbstractPublishType;

class OtherType extends AbstractPublishType
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
        // todo
    }
}