<?php
declare(strict_types=1);

namespace Workbunny\WebmanPushServer\Commands;

use ReflectionException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Workbunny\WebmanPushServer\ApiRoute;
use Workbunny\WebmanPushServer\HookServer;

class HookResetCommand extends Command
{
    protected static $defaultName = 'workbunny:push-server-hreset';
    protected static $defaultDescription = 'Reset Hook Server Redis Stream';

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            HookServer::getStorage()->del(HookServer::getConfig('queue_key'));
            return self::SUCCESS;
        }catch (\RedisException $exception){
            return self::FAILURE;
        }
    }
}
