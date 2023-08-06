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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use RedisException;
use RuntimeException;
use support\Db;
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

    /** @var HookServer|null  */
    protected static ?HookServer $_instance = null;

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
     * @return HookServer
     */
    public static function instance(): HookServer
    {
        if (!self::$_instance instanceof HookServer) {
            self::$_instance = new HookServer();
        }
        return self::$_instance;
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

    /**
     * 发布消息
     *
     * @param string $event 事件
     * @param array $data 数据
     * @param string|null $republishCountKey 重入队列计数器
     * @return bool|null
     */
    public function publish(string $event, array $data, ?string $republishCountKey = null): ?bool
    {
        $queue = self::getConfig('queue_key');
        $queueLimit = self::getConfig('queue_limit', 0);
        $value = [
            'name'   => $event,
            'data'   => json_encode($data,JSON_UNESCAPED_UNICODE),
            'time'   => microtime(true),
        ];
        if ($republishCountKey) {
            $value[$republishCountKey] = ($value[$republishCountKey] ?? 0) + 1;
        }
        try {
            // 重入队列不受queue size限制
            if ($republishCountKey !== null and $queueLimit !== 0 and self::getStorage()->xLen($queue) >= $queueLimit) {
                throw new RuntimeException("Queue $queue size limited. ", -1);
            }
            return boolval(self::getStorage()->xAdd($queue,'*', $value));
        } catch (RedisException $exception) {
            Log::channel('plugin.workbunny.webman-push-server.notice')->warning('Redis server error. ', [
                'message' => $exception->getMessage(), 'code' => $exception->getCode(),
                'queue'   => $queue, 'value'   => $value
            ]);
            $this->_tempInsert($queue, $value);
            return false;
        } catch (RuntimeException $exception) {
            Log::channel('plugin.workbunny.webman-push-server.notice')->notice('Publish failed. ', [
                'message' => $exception->getMessage(), 'code' => $exception->getCode(),
                'queue'   => $queue, 'value'   => $value
            ]);
            $this->_tempInsert($queue, $value);
            return null;
        }
    }

    /**
     * 队列ack
     *
     * @param string $queue
     * @param string $group
     * @param array $idArray
     * @return void
     * @throws RedisException
     */
    public function ack(string $queue, string $group, array $idArray): void
    {
        if(self::getStorage()->xAck($queue, $group, $idArray)){
            self::getStorage()->xDel($queue, $idArray);
        }
    }

    /**
     * @return void
     */
    protected function _tempInit()
    {
        $builder = Schema::connection('plugin.workbunny.webman-push-server.local-storage');
        if (!$builder->hasTable('temp')) {
            $builder->create('temp', function (Blueprint $table) {
                $table->id();
                $table->string('queue');
                $table->json('data');
                $table->integer('create_at');
            });
            echo 'local-storage db created. ' . PHP_EOL;
        }
    }

    /**
     * @param string $queue
     * @param array $value
     * @return void
     */
    protected function _tempInsert(string $queue, array $value)
    {
        // 数据储存至文件
        Db::connection('plugin.workbunny.webman-push-server.local-storage')
            ->table('temp')->insert([
                'queue'      => $queue,
                'data'       => json_encode($value, JSON_UNESCAPED_UNICODE),
                'created_at' => time()
            ]);
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

    /** @inheritDoc */
    public function onWorkerStart(Worker $worker): void
    {
        // 初始化temp库
        $this->_tempInit();
        // 设置消息重载定时器
        $this->_requeueTimer = Timer::add(self::getConfig('requeue_interval'), function () {
            $connection = Db::connection('plugin.workbunny.webman-push-server.local-storage');
            $connection->table('temp')->select()->chunkById(500, function (Collection $collection) use ($connection) {
                foreach ($collection as $item) {
                    if (self::getStorage()->xAdd($item->queue,'*', json_decode($item->data, true))) {
                        $connection->table('temp')->delete($item->id);
                    }
                }
            });
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
                            // 数据ack
                            $this->ack($queue, $group, $idArray);
                            // 失败数据重入队尾
                            if($response->getStatusCode() !== 200) {
                                foreach ($data as $value) {
                                    $this->publish($queue, $value, 'failed_count');
                                }
                            }
                        }, function (\Throwable $throwable) use ($queue, $group, $idArray, $data) {
                            // 数据ack
                            $this->ack($queue, $group, $idArray);
                            // 重入队尾
                            foreach ($data as $value) {
                                $this->publish($queue, $value, 'error_count');
                            }
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