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

namespace Workbunny\WebmanPushServer;

use Closure;
use RedisException;
use support\Log;
use support\Redis;
use Tests\MockClass\MockRedisStream;
use Workerman\Connection\TcpConnection;
use Workerman\Http\Client;
use Workerman\Http\Response;
use Workerman\Timer;
use Workerman\Worker;

class HookServer implements ServerInterface
{
    /** @var \Redis|null  */
    protected static ?\Redis $_storage = null;

    /** @var Client|null HTTP-client */
    protected static ?Client $_client = null;

    /** @var int  */
    protected static int $_connectTimeout = 30;

    /** @var int  */
    protected static int $_requestTimeout = 30;

    /** @var int|null 消费定时器 */
    protected ?int $_consumerTimer = null;

    /** @var int|null 消息重入队列定时器 */
    protected ?int $_requeueTimer = null;

    /** @inheritDoc */
    public static function getConfig(string $key, $default = null)
    {
        return Server::isDebug() ?
            config('plugin.workbunny.webman-push-server.app.hook-server.' . $key, $default) :
            \config('plugin.workbunny.webman-push-server.app.hook-server.' . $key, $default);
    }

    /** @inheritDoc */
    public static function getStorage(): \Redis
    {
        if(!self::$_storage instanceof \Redis){
            self::$_storage = Server::isDebug() ?
                new MockRedisStream() :
                Redis::connection(self::getConfig('redis_channel', 'default'))->client();
        }
        return self::$_storage;
    }

    /**
     * @return Client
     */
    public static function getClient(): Client
    {
        if(!self::$_client instanceof Client){
            self::$_client = new Client([
                'connect_timeout' => self::$_connectTimeout,
                'timeout'         => self::$_requestTimeout,
            ]);
        }
        return self::$_client;
    }

    /**
     * @param string $event
     * @param array $data
     * @return bool|null
     * @throws RedisException
     */
    public static function publish(string $event, array $data): ?bool
    {
        $queue = self::getConfig('queue_key');
        $queueLimit = self::getConfig('queue_limit', 0);
        if($queueLimit !== 0 and self::getStorage()->xLen($queue) >= $queueLimit){
            return null;
        }
        return boolval(self::getStorage()->xAdd($queue,'*', [
            'name'   => $event,
            'data'   => json_encode($data,JSON_UNESCAPED_UNICODE),
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
        if(self::getStorage()->xAck($queue, $group, $idArray)){
            self::getStorage()->xDel($queue, $idArray);
        }
    }

    /**
     * @param string $method
     * @param array $options = = [
     *  'header'  => [],
     *  'query'   => [],
     *  'data'    => '',
     * ]
     * @param Closure|null $success = function(\Workerman\Http\Response $response){}
     * @param Closure|null $error = function(\Exception $exception){}
     * @return void
     */
    protected function _request(string $method, array $options = [], ?Closure $success = null, ?Closure $error = null) : void
    {
        $queryString = http_build_query($options['query'] ?? []);
        $headers = array_merge($options['header'] ?? [], [
            'Connection'   => 'keep-alive',
            'Server'       => 'workbunny-push-server',
            'Version'      => VERSION,
            'Content-type' => 'application/json'
        ]);
        self::getClient()->request(
            sprintf('%s?%s', self::getConfig('webhook_url'), $queryString),
            [
                'method'    => $method,
                'version'   => '1.1',
                'headers'   => $headers,
                'data'      => $options['data'] ?? '{}',
                'success'   => $success ?? function (Response $response) {},
                'error'     => $error ?? function (\Exception $exception) {}
            ]
        );
    }

    /**
     * 尝试将数据发布到队尾
     *
     * @param string $queue
     * @param array $data
     * @param string $countName
     * @return void
     */
    protected function _tryToRepublish(string $queue, array $data, string $countName): void
    {
        try {
            foreach ($data as $value){
                $value[$countName] = ($value[$countName] ?? 0) + 1;
                self::getStorage()->xAdd($queue,'*', $value);
            }
        } catch (RedisException $exception) {
            Log::channel('plugin.workbunny.webman-push-server.notice')->notice($exception->getMessage(), [
                'code' => $exception->getCode(),
            ]);
            // todo 将数据储存至文件
        }
    }

    /**
     * @param string $secret
     * @param string $method
     * @param array $query
     * @param string $body
     * @return string
     */
    public static function sign(string $secret, string $method, array $query, string $body): string
    {
        ksort($query);
        return hash_hmac('sha256',
            $method . PHP_EOL . \parse_url(self::getConfig('webhook_url'), \PHP_URL_PATH) . PHP_EOL . http_build_query($query) . PHP_EOL . $body,
            $secret,
            false
        );
    }

    /** @inheritDoc */
    public function onWorkerStart(Worker $worker): void
    {
        // 设置消息重载定时器
        $this->_requeueTimer = Timer::add(self::getConfig('requeue_interval'), function () {
            // todo 读取文件数据
            // todo 将文件数据发布至队列
        });
        // 设置消费定时器
        $this->_consumerTimer = Timer::add($interval = self::getConfig('consumer_interval') / 1000, function () use ($worker, $interval) {
            try {
                // 创建组
                self::getStorage()->xGroup(
                    'CREATE', $queue = self::getConfig('queue_key'),
                    $group = "$queue:webhook-group", '0', true
                );
                // 读取未确认的消息组
                if(
                    $res = self::getStorage()->xReadGroup(
                        $group, "webhook-consumer-$worker->id", [$queue => '>'],
                        self::getConfig('prefetch_count'), (int)($interval * 1000)
                    )
                ) {
                    // 队列组
                    foreach ($res as $queue => $data) {
                        $idArray = array_keys($data);
                        $messageArray = array_values($data);
                        // http发送
                        $this->_request($method = 'POST', [
                            'header' => [
                                'sign' => self::sign(self::getConfig('webhook_secret'), $method, $query = ['id' => uuid()], $body = json_encode([
                                    'time_ms' => microtime(true),
                                    'events'  => $messageArray,
                                ]))
                            ],
                            'query'  => $query,
                            'data'   => $body,
                        ], function (Response $response) use ($queue, $group, $idArray, $data) {
                            // 先对数据进行ack
                            self::ack($queue, $group, $idArray);
                            // 后将失败数据重入队尾
                            if($response->getStatusCode() !== 200) {
                                $this->_tryToRepublish($queue, $data, 'failed_count');
                            }
                        }, function (\Throwable $throwable) use ($queue, $group, $idArray, $data) {
                            // 先对数据进行ack
                            self::ack($queue, $group, $idArray);
                            // 重入队尾
                            $this->_tryToRepublish($queue, $data, 'error_count');
                            // 错误日志
                            Log::channel('plugin.workbunny.webman-push-server.error')->error($throwable->getMessage(), [
                                'code'  => $throwable->getCode(),
                                'file'  => $throwable->getFile() . ':' . $throwable->getLine(),
                                'trace' => $throwable->getTrace()
                            ]);
                        });
                    }
                }
            } catch (RedisException $exception) {
                // 错误日志
                Log::channel('plugin.workbunny.webman-push-server.warning')->warning($exception->getMessage(), [
                    'code'  => $exception->getCode()
                ]);
            }
        });
    }

    /** @inheritDoc */
    public function onWorkerStop(Worker $worker): void
    {
        if($this->_consumerTimer){
            Timer::del($this->_consumerTimer);
            $this->_consumerTimer = null;
        }
        try {
            self::getStorage()->close();
            self::$_storage = null;
        } catch (RedisException $exception) {}
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