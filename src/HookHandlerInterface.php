<?php declare(strict_types=1);

namespace Workbunny\WebmanPushServer;

interface HookHandlerInterface
{
    /**
     * @return HookHandlerInterface
     */
    public static function instance(): HookHandlerInterface;

    /**
     * @param string $queue
     * @param string $group
     * @param array $dataArray
     * @return mixed
     */
    public function run(string $queue, string $group, array $dataArray);
}