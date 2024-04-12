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
use Exception;
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
use Throwable;
use Workerman\Connection\TcpConnection;
use Workerman\Http\Client;
use Workerman\Http\Response;
use Workerman\Timer;
use Workerman\Worker;

class HookServer implements ServerInterface
{
    /** @var \Redis|null  */
    protected static ?\Redis $_storage = null;
    /** @var HookServer|null  */
    protected static ?HookServer $_instance = null;
    /** @var int|null 消费定时器 */
    protected ?int $_consumerTimer = null;
    /** @var int|null 重入队列定时器 */
    protected ?int $_requeueTimer = null;
    /** @var int|null pending处理定时器 */
    protected ?int $_claimTimer = null;

    /** @var array 队列分组下次claim的游标 */
    protected array $claimStartTags = [];

    /** @inheritDoc */
    public static function getConfig(string $key, $default = null)
    {
        return config('plugin.workbunny.webman-push-server.app.hook-server.' . $key, $default);
    }

    /** @inheritDoc */
    public static function getStorage(): \Redis
    {
        if(!self::$_storage instanceof \Redis){
            self::$_storage = Redis::connection(self::getConfig('redis_channel', 'default'))->client();
        }
        return self::$_storage;
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
     * @return bool
     */
    public function ack(string $queue, string $group, array $idArray): bool
    {
        try {
            if (self::getStorage()->xAck($queue, $group, $idArray)) {
                self::getStorage()->xDel($queue, $idArray);
            }
            return true;
        } catch (RedisException $exception) {
            Log::channel('plugin.workbunny.webman-push-server.warning')->warning('Ack failed. ', [
                'message' => $exception->getMessage(), 'code' => $exception->getCode(),
                'queue' => $queue, 'group' => $group, 'ids' => $idArray
            ]);
        }
        return false;
    }

    /**
     * @param string $queue
     * @param string $group
     * @param string $consumer
     * @return void
     */
    public function claim(string $queue, string $group, string $consumer)
    {
        if (!method_exists(self::getStorage(), 'xAutoClaim')) {
            Log::channel('plugin.workbunny.webman-push-server.warning')->warning(
                'Method xAutoClaim requires redis-server >= 6.2.0. '
            );
            return;
        }
        $pendingTime = self::getConfig('pending_timeout', 0) * 1000;
        if ($pendingTime <= 0) {
            return;
        }
        try {
            if ($idArray = self::getStorage()->xAutoClaim(
                $queue, $group, $consumer, $pendingTime,
                $this->claimStartTags[$queue][$group][$consumer] ?? '0-0', -1, true
            )) {
                $this->claimStartTags[$queue][$group][$consumer] = $idArray[0] ?? '0-0';
                $idArray = $idArray[2] ?? [];
                if ($idArray) {
                    if (self::getStorage()->xAck($queue, $group, $idArray)) {
                        self::getStorage()->xDel($queue, $idArray);
                    }
                }
            }
        } catch (RedisException $exception) {
            Log::channel('plugin.workbunny.webman-push-server.warning')->warning('Claim failed. ', [
                'message' => $exception->getMessage(), 'code' => $exception->getCode(),
                'queue' => $queue, 'group' => $group,
                'consumer' => $consumer, 'ids' => $idArray ?? []
            ]);
        }
    }

    /**
     * 消费
     *
     * @param string $queue
     * @param string $group
     * @param string $consumer
     * @param int $blockTime
     * @return void
     * @throws Exception
     */
    public function consumer(string $queue, string $group, string $consumer, int $blockTime)
    {
        try {
            // 创建组
            self::getStorage()->xGroup('CREATE', $queue, $group, '0', true);
            // 读取未确认的消息组
            if ($res = self::getStorage()->xReadGroup(
                $group, $consumer, [$queue => '>'], self::getConfig('prefetch_count'), $blockTime
            )) {
                try {
                    $class = self::getConfig('hook_handler', WebhookHandler::class);
                    if (!is_subclass_of($class, HookHandlerInterface::class)) {
                        throw new Exception("Hook handler $class error. ");
                    }
                    // 队列组
                    foreach ($res as $queue => $data) {
                        $class::instance()->run($queue, $group, $data);
                    }
                } catch (Throwable $throwable) {
                    // 错误日志
                    Log::channel('plugin.workbunny.webman-push-server.error')->error('Hook handler error. ', [
                        'message' => $throwable->getMessage(), 'code' => $throwable->getCode(),
                        'file'  => $throwable->getFile() . ':' . $throwable->getLine(),
                        'trace' => $throwable->getTrace()
                    ]);
                    throw new Exception($throwable->getMessage(), $throwable->getCode(), $throwable);
                }
            }
        } catch (RedisException $exception) {
            Log::channel('plugin.workbunny.webman-push-server.warning')->warning('Storage consumer error. ', [
                'message' => $exception->getMessage(), 'code' => $exception->getCode()
            ]);
        }
    }

    /**
     * @return void
     */
    protected function _tempInit()
    {
        $config = config('database.connections')['plugin.workbunny.webman-push-server.local-storage'] ?? [];
        if ($config) {
            // 创建temp数据库文件
            if (!file_exists($file = $config['database'])) {
                // 创建目录
                if (!is_dir($dir = dirname($file))) {
                    \mkdir($dir, 0777, true);
                }
                \touch($file);
            }
            // 创建数据库结构
            $builder = Db::schema('plugin.workbunny.webman-push-server.local-storage');
            if (!$builder->hasTable('temp')) {
                try {
                    $builder->create('temp', function (Blueprint $table) {
                        $table->id();
                        $table->string('queue');
                        $table->json('data');
                        $table->integer('create_at');
                    });
                    echo 'local-storage db created. ' . PHP_EOL;
                } catch (Throwable $throwable) {}
            }
        }
    }

    /**
     * @param string $queue
     * @param array $value
     * @return int
     */
    protected function _tempInsert(string $queue, array $value): int
    {
        $config = config('database.connections')['plugin.workbunny.webman-push-server.local-storage'] ?? [];
        if ($config) {
            // 数据储存至文件
            return Db::connection('plugin.workbunny.webman-push-server.local-storage')
                ->table('temp')->insertGetId([
                    'queue'      => $queue,
                    'data'       => json_encode($value, JSON_UNESCAPED_UNICODE),
                    'created_at' => time()
                ]);
        }
        return 0;
    }

    /** @inheritDoc */
    public function onWorkerStart(Worker $worker): void
    {
        $queue = self::getConfig('queue_key');
        $group = "$queue:event-hook-group";
        $consumer = "$group:$worker->id";
        // 初始化temp库
        $this->_tempInit();
        // 设置消息重载定时器
        $interval = self::getConfig('requeue_interval', 0);
        if ($interval > 0 and config('database.connections')['plugin.workbunny.webman-push-server.local-storage'] ?? []) {
            $this->_requeueTimer = Timer::add(
                $interval,
                function () {
                    $connection = Db::connection('plugin.workbunny.webman-push-server.local-storage');
                    $connection->table('temp')->select()->chunkById(500, function (Collection $collection) use ($connection) {
                        foreach ($collection as $item) {
                            if (self::getStorage()->xAdd($item->queue,'*', json_decode($item->data, true))) {
                                $connection->table('temp')->delete($item->id);
                            }
                        }
                    });
                });
        }
        // 设置pending处理定时器
        $interval = self::getConfig('claim_interval', 0);
        if ($interval > 0) {
            $this->_claimTimer = Timer::add(
                $interval,
                function () use ($queue, $group, $consumer) {
                    // 处理pending消息
                    $this->claim($queue, $group, $consumer);
                }
            );
        }
        // 设置消费定时器
        $this->_consumerTimer = Timer::add(
            $interval = self::getConfig('consumer_interval', 1) / 1000,
            function () use ($worker, $interval, $queue, $group, $consumer) {
                // 执行消费
                $this->consumer($queue, $group, $consumer, (int)($interval * 1000));
            });
    }

    /** @inheritDoc */
    public function onWorkerStop(Worker $worker): void
    {
        if ($this->_requeueTimer) {
            Timer::del($this->_requeueTimer);
            $this->_requeueTimer = null;
        }
        if ($this->_consumerTimer) {
            Timer::del($this->_consumerTimer);
        }
        try {
            self::getStorage()->close();
        } catch (RedisException $exception) {
            Log::channel('plugin.workbunny.webman-push-server.warning')->warning('Storage close error. ', [
                'message' => $exception->getMessage(), 'code' => $exception->getCode()
            ]);
        } finally {
            $this->_requeueTimer =
            $this->_consumerTimer =
            self::$_storage = null;
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