<?php declare(strict_types=1);

namespace Workbunny\WebmanPushServer;

class DefaultHandler implements HookHandlerInterface
{

    /** @var DefaultHandler|null  */
    protected static ?DefaultHandler $_instance = null;

    /** @inheritdoc  */
    public static function instance(): DefaultHandler
    {
        if(!self::$_instance instanceof DefaultHandler){
            self::$_instance = new DefaultHandler();
        }
        return self::$_instance;
    }

    /** @inheritdoc  */
    public function run(string $queue, string $group, array $dataArray): void
    {
        $idArray = array_keys($dataArray);
        HookServer::instance()->ack($queue, $group, $idArray);
    }
}