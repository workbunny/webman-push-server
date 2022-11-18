<?php
declare(strict_types=1);

namespace Workbunny\WebmanPushServer;

use Workbunny\WebmanPushServer\Apis\AbstractApis;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Workerman\Worker;

class ApiService extends AbstractServer
{
    protected array $_header = [
        'Content-Type' => 'application/json',
    ];

    /** @inheritDoc */
    public function onWorkerStart(Worker $worker): void
    {}

    /** @inheritDoc */
    public function onWorkerStop(Worker $worker): void
    {}

    /** @inheritDoc */
    public function onConnect(TcpConnection $connection): void
    {}

    /** @inheritDoc */
    public function onClose(TcpConnection $connection): void
    {}

    /** @inheritDoc */
    public function onMessage(TcpConnection $connection, $data): void
    {
        if(!$data instanceof Request){
            $connection->send(new Response(400, [], 'Bad Request. '));
        }
        if (!($appKey = $data->get('auth_key'))) {
            $connection->send(new Response(400, [], 'Bad Request'));
            return;
        }
        if(!$this->getConfig('app_query')($appKey)){
            $connection->send(new Response(401, [], 'Invalid app_key'));
            return;
        }
        $explode = explode('/', trim($path = $data->path(), '/'));
        if (count($explode) < 3) {
            $connection->send(new Response(400, [], 'Bad Request'));
            return;
        }
        ;
        $params = $data->get();
        unset($params['auth_signature']);
        ksort($params);
        $realAuthSignature = hash_hmac(
            'sha256',
            $data->method()."\n" . $path . "\n" . self::_array_implode('=', '&', $params),
            $this->getConfig('app_query')($appKey)['app_secret'],
            false
        );
        if ($data->get('auth_signature') !== $realAuthSignature) {
            $connection->send(new Response(401, [], 'Invalid signature'));
            return;
        }

        if($response = AbstractApis::factory($explode[2])) {
            $response->response($appKey, $this->getServer(), $connection, $data);
            return;
        }
        $connection->send(new Response(404, [], "Not Found [$explode[2]"));
    }

    /**
     * array_implode
     * @param string $glue
     * @param string $separator
     * @param array $array
     * @return string
     */
    protected static function _array_implode(string $glue, string $separator, array $array): string
    {
        $string = [];
        foreach ($array as $key => $val) {
            if (is_array($val)) {
                $val = implode(',', $val);
            }
            $string[] = "{$key}{$glue}{$val}";
        }

        return implode($separator, $string);
    }
}