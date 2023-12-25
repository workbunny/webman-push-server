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

class ApisCommand extends Command
{
    protected static $defaultName = 'workbunny:push-server-apis';
    protected static $defaultDescription = 'Push Server APIs';

    /**
     * @return void
     */
    protected function configure()
    {
        $this->setName('workbunny:push-server-apis')
            ->setDescription('Push Server APIs. ');
        $this->addOption('all', 'a', InputOption::VALUE_NONE, 'Show all');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws ReflectionException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $all = $input->getOption('all');
        $headers = $all ? ['uri', 'method', 'handler', 'middlewares'] : ['uri', 'method', 'handler'];
        $rows = [];
        foreach (ApiRoute::getRoutes() as $route) {
            $method = $route['method'];
            /** @var \Closure $handler */
            $handler = $route['handler'];
            $ref = new \ReflectionFunction($handler);
            $handlerFile = $ref->getFileName() . ' ' . $ref->getStartLine() . ' - ' . $ref->getEndLine();

            if($all){
                $middlewares = ApiRoute::getMiddlewares(ApiRoute::getMiddlewareTag($handler))[$method] ?? [];
                $mids = [];
                /** @var \Closure $middleware */
                foreach ($middlewares as $middleware){
                    $ref = new \ReflectionFunction($middleware);
                    $mids[] = $ref->getFileName() . ' ' . $ref->getStartLine() . ' - ' . $ref->getEndLine();
                }

                $rows[] = [$route['uri'], $method, $handlerFile, $mids ? implode(PHP_EOL, $mids) : '--'];
            }else{
                $rows[] = [$route['uri'], $method, $handlerFile];
            }
        }

        $table = new Table($output);
        $table->setHeaders($headers);
        $table->setRows($rows);
        $table->render();
        return self::SUCCESS;
    }
}
