<?php
declare(strict_types=1);

namespace Workbunny\WebmanPushServer\Services;

use Closure;
use Redis;
use RedisException;
use Workerman\Connection\TcpConnection;
use Workerman\Http\Client;
use Workerman\Http\Response;
use Workerman\Timer;
use Workerman\Worker;
use function Workbunny\WebmanPushServer\uuid;

class Hook extends AbstractService
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
     * @param Redis $client
     * @param string $event
     * @param array $data
     * @return bool|null
     * @throws RedisException
     */
    public static function publish(Redis $client, string $event, array $data): ?bool
    {
        $config = config('plugin.workbunny.webman-push-server.services')[self::class];
        if($client->xLen($queue = 'workbunny:webman-push-server:webhook-stream') >= $config['extra']['queue_limit']){
            return null;
        }
        return boolval($client->xAdd($queue,'*', [
            'name'   => $event,
            'data'   => $data,
            'time'   => microtime(true),
        ]));
    }

    /**
     * 队列ack
     * @param Redis $client
     * @param string $queue
     * @param string $group
     * @param array $idArray
     * @return void
     * @throws RedisException
     */
    public static function ack(Redis $client, string $queue, string $group, array $idArray): void
    {
        if($client->xAck($queue, $group, [$idArray])){
            $client->xDel($queue, $idArray);
        }
    }

    /**
     * 默认hook处理器
     * @param Redis $client
     * @param string $queue
     * @param string $group
     * @param array $data
     * @return void
     */
    protected function _defaultHandler(Redis $client, string $queue, string $group, array $data): void
    {
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
            ], function (Response $response) use ($client, $queue, $group, $idArray, $data){
                if($response->getStatusCode() !== 200){
                    // 重入队尾
                    foreach ($data as $value){
                        $value['failed_count'] = ($value['failed_count'] ?? 0) + 1;
                        $client->xAdd($queue,'*', $value);
                    }
                }
                self::ack($client, $queue, $group, $idArray);
            });
        }catch (\Throwable $throwable){}
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
            'Connection' => 'keep-alive'
        ]);
        $this->_client->request(
            sprintf(
                'http://%s:%d%s?%s',
                $this->getExtra('hook_host'),
                $this->getExtra('hook_port'),
                $this->getExtra('hook_uri'),
                $queryString
            ),
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
        return hash_hmac(
            'sha256',
            $method . PHP_EOL . $this->getExtra('hook_uri') . PHP_EOL . http_build_query($query) . PHP_EOL . json_encode($data),
            $this->getExtra('hook_secret'),
            false
        );
    }

    /** @inheritDoc */
    public function onWorkerStart(Worker $worker): void
    {
        $this->_timer = Timer::add($interval = 0.001, function () use ($worker, $interval){
            $client = $this->getServer()->getStorage();
            // 创建组
            $client->xGroup('CREATE', $queue = 'workbunny:webman-push-server:webhook-stream', $group = "workbunny:webman-push-server:webhook-group", '0', true);
            // 读取未确认的消息组
            if($res = $client->xReadGroup($group, "webhook-consumer-$worker->id", [$queue => '>'], $this->getConfig('prefetch_count', 1), (int)($interval * 1000))){
                // 队列组
                foreach ($res as $queue => $data){
                    $res = $this->getConfig('hook_handler')($client, $queue, $group, $data);
                    if(is_callable($res)) {
                        $res($client, $queue, $group, $data);
                    }elseif (
                        isset($res['hook_host']) and
                        isset($res['hook_port']) and
                        isset($res['hook_uri']) and
                        isset($res['hook_secret'])
                    ){
                        $this->setExtra('hook_host', $res['hook_host']);
                        $this->setExtra('hook_port', $res['hook_port']);
                        $this->setExtra('hook_uri', $res['hook_uri']);
                        $this->setExtra('hook_secret', $res['hook_secret']);
                        $this->_defaultHandler($client, $queue, $group, $data);
                    }else{
                        echo 'Hook Handler Result Error.' . PHP_EOL;
                    }
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