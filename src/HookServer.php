<?php
declare(strict_types=1);

namespace Workbunny\WebmanPushServer;

use Closure;
use RedisException;
use Workerman\Connection\TcpConnection;
use Workerman\Http\Client;
use Workerman\Http\Response;
use Workerman\Timer;
use Workerman\Worker;

class HookServer extends AbstractServer
{
    /** @var int|null  */
    protected ?int $_timer = null;

    /** @var Client|null  */
    protected ?Client $_client = null;

    /** @var int  */
    protected int $_connectTimeout = 30;

    /** @var int  */
    protected int $_requestTimeout = 30;

    /**
     * @param string $event
     * @param array $data
     * @return bool|null
     * @throws RedisException
     */
    public static function publish(string $event, array $data): ?bool
    {
        $config = config('plugin.workbunny.webman-push-server.process.hook-server.constructor.config.queue_config');
        if(Server::getServer()->getStorage()->xLen($queue = $config['queue_key']) >= $config['queue_limit']){
            return null;
        }
        return boolval(Server::getServer()->getStorage()->xAdd($queue,'*', [
            'name'   => $event,
            'data'   => $data,
            'time'   => microtime(true),
        ]));
    }

    /**
     * 队列ack
     * @param string $queue
     * @param string $group
     * @param array $idArray
     * @return void
     * @throws RedisException
     */
    public static function ack(string $queue, string $group, array $idArray): void
    {
        if(Server::getServer()->getStorage()->xAck($queue, $group, [$idArray])){
            Server::getServer()->getStorage()->xDel($queue, $idArray);
        }
    }

    /**
     * @param string $method
     * @param array $options = = [
     *  'header'  => [],
     *  'query'   => [],
     *  'data'    => [],
     * ]
     * @param Closure|null $success = function(\Workerman\Http\Response $response){}
     * @param Closure|null $error = function(\Exception $exception){}
     * @return void
     */
    protected function _request(string $method, array $options = [], ?Closure $success = null, ?Closure $error = null) : void
    {
        if(!$this->_client instanceof Client){
            $this->_client = new Client([
                'connect_timeout' => $this->_connectTimeout,
                'timeout'         => $this->_requestTimeout,
            ]);
        }
        $queryString = http_build_query($options['query'] ?? []);
        $headers = array_merge($options['header'] ?? [], [
            'Connection' => 'keep-alive',
            'Server'     => 'workbunny-push-server'
        ]);
        $config = $this->getConfig('webhook_config', []);
        $this->_client->request(
            sprintf('%s?%s', $config['webhook_url'], $queryString),
            [
                'method'    => $method,
                'version'   => '1.1',
                'headers'   => $headers,
                'data'      => $options['data'] ?? [],
                'success'   => $success ?? function (Response $response) {},
                'error'     => $error ?? function (\Exception $exception) {}
            ]
        );
    }

    /**
     * @param string $method
     * @param array $query
     * @param array $data
     * @return string
     */
    protected function _sign(string $method, array $query, array $data): string
    {
        $config = $this->getConfig('webhook_config');
        return hash_hmac(
            'sha256',
            $method . PHP_EOL . \parse_url($config['webhook_url'], \PHP_URL_PATH) . PHP_EOL . http_build_query($query) . PHP_EOL . json_encode($data),
            $config['webhook_secret'],
            false
        );
    }

    /** @inheritDoc */
    public function onWorkerStart(Worker $worker): void
    {
        $this->_timer = Timer::add($interval = 0.001, function () use ($worker, $interval){
            $config = $this->getConfig('queue_config');
            // 创建组
            Server::getServer()->getStorage()->xGroup('CREATE', $queue = $config['queue_key'], $group = "$queue:webhook-group", '0', true);
            // 读取未确认的消息组
            if($res = Server::getServer()->getStorage()->xReadGroup($group, "webhook-consumer-$worker->id", [$queue => '>'], $config['queue_config'], (int)($interval * 1000))){
                // 队列组
                foreach ($res as $queue => $data){
                    $idArray = array_keys($data);
                    $messageArray = array_values($data);
                    try {
                        $this->_request($method = 'POST', [
                            'header' => [
                                'sign' => $this->_sign($method, $query = [
                                    'id' => uuid(),
                                ], $data = [
                                    'time_ms' => microtime(true),
                                    'events'  => $messageArray,
                                ])
                            ],
                            'query'  => $query,
                            'data'   => $data,
                        ], function (Response $response) use ($queue, $group, $idArray, $data){
                            if($response->getStatusCode() !== 200){
                                // 重入队尾
                                foreach ($data as $value){
                                    $value['failed_count'] = ($value['failed_count'] ?? 0) + 1;
                                    Server::getServer()->getStorage()->xAdd($queue,'*', $value);
                                }
                            }
                            self::ack($queue, $group, $idArray);
                        });
                    }catch (\Throwable $throwable){}
                }
            }
        });
    }

    /** @inheritDoc */
    public function onWorkerStop(Worker $worker): void
    {
        if($this->_timer){
            Timer::del($this->_timer);
            $this->_timer = null;
        }
    }

    /** @inheritDoc */
    public function onConnect(TcpConnection $connection): void
    {}

    /** @inheritDoc */
    public function onClose(TcpConnection $connection): void
    {}

    /** @inheritDoc */
    public function onMessage(TcpConnection $connection, $data): void
    {}
}