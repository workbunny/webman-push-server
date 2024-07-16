<?php declare(strict_types=1);

namespace Tests\Examples;

use Workbunny\WebmanPushServer\Events\AbstractEvent;
use Workerman\Connection\TcpConnection;

class OtherEvent extends AbstractEvent
{
    /**
     * @inheritDoc
     */
    public function response(TcpConnection $connection, array $request): void
    {
        // todo
    }
}