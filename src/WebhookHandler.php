<?php declare(strict_types=1);

namespace Workbunny\WebmanPushServer;

use Closure;
use support\Log;
use Throwable;
use Workerman\Http\Client;
use Workerman\Http\Response;

class WebhookHandler implements HookHandlerInterface
{

    /** @var WebhookHandler|null  */
    protected static ?WebhookHandler $_instance = null;

    /** @var Client|null HTTP-client */
    protected ?Client $_client = null;

    /** @inheritdoc  */
    public static function instance(): WebhookHandler
    {
        if(!self::$_instance instanceof WebhookHandler){
            self::$_instance = new WebhookHandler();
        }
        return self::$_instance;
    }

    /** @inheritdoc  */
    public function run(string $queue, string $group, array $dataArray)
    {
        $idArray = array_keys($dataArray);
        $messageArray = array_values($dataArray);
        // 如果没有配置webhook地址，直接ack
        if (!HookServer::getConfig('webhook_url')) {
            HookServer::instance()->ack($queue, $group, $idArray);
        }
        // http发送
        $this->_request($method = 'POST', [
            'header' => [
                'sign' => HookServer::sign(HookServer::getConfig('webhook_secret'), $method, $query = ['id' => uuid()], $body = json_encode([
                    'time_ms' => microtime(true),
                    'events'  => $messageArray,
                ]))
            ],
            'query'  => $query,
            'data'   => $body,
        ], function (Response $response) use ($queue, $group, $idArray, $dataArray) {
            // 数据ack
            if (HookServer::instance()->ack($queue, $group, $idArray)) {
                // 失败数据重入队尾
                if($response->getStatusCode() !== 200) {
                    foreach ($dataArray as $value) {
                        HookServer::instance()->publish($queue, $value, 'failed_count');
                    }
                }
            }
        }, function (Throwable $throwable) use ($queue, $group, $idArray, $dataArray) {
            // 数据ack
            if (HookServer::instance()->ack($queue, $group, $idArray)) {
                // 重入队尾
                foreach ($dataArray as $value) {
                    HookServer::instance()->publish($queue, $value, 'error_count');
                }
            }
        });
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
        if (!$this->_client) {
            $this->_client = new Client([
                'connect_timeout' => HookServer::getConfig('webhook_connect_timeout', 30),
                'timeout'         => HookServer::getConfig('webhook_request_timeout', 30),
            ]);
        }
        $this->_client->request(
            sprintf('%s?%s', HookServer::getConfig('webhook_url'), $queryString),
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
}