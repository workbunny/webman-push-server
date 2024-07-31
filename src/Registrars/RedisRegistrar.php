<?php declare(strict_types=1);
/**
 * @author workbunny/Chaz6chez
 * @email chaz6chez1993@outlook.com
 */

namespace Workbunny\WebmanPushServer\Registrars;

use support\Log;
use support\Redis;

class RedisRegistrar implements RegistrarInterface
{
    /** @inheritDoc */
    public function get(string $name, string $ip, int $port): ?array
    {
        $client = Redis::connection('plugin.workbunny.webman-push-server.server-register')->client();
        try {
            $result = $client->hGetAll($this->_registrarKey($name, $ip, $port));
            foreach ($result as &$value) {
                $value = json_decode($value, true);
            }
            return $result;
        } catch (\RedisException $exception) {
            Log::channel('plugin.workbunny.webman-push-server.warning')
                ->warning("[REGISTRAR] Get failed. ", [
                    'method'  => __METHOD__,
                    'params'  => func_get_args(),
                    'message' => $exception->getMessage(),
                    'code'    => $exception->getCode(),
                ]);
            return null;
        }
    }

    /** @inheritDoc */
    public function query(?string $name): ?array
    {
        $client = Redis::connection('plugin.workbunny.webman-push-server.server-register')->client();
        $hash = [];
        try {
            while(
                false !== ($keys = $client->scan($iterator, $this->_registrarKey($name, null, null),100))
            ) {
                foreach ($keys as $key) {
                    $result = $client->hGetAll($key);
                    foreach ($result as &$value) {
                        $value = json_decode($value, true);
                    }
                    $hash[$this->_getName($key)][$this->_getIp($key) . ':' . $this->_getPort($key)] = $result;
                }
            }
            return $hash;
        } catch (\RedisException $exception) {
            Log::channel('plugin.workbunny.webman-push-server.warning')
                ->warning("[REGISTRAR] Query failed. ", [
                    'method'  => __METHOD__,
                    'params'  => func_get_args(),
                    'message' => $exception->getMessage(),
                    'code'    => $exception->getCode(),
                ]);
            return null;
        }
    }

    /** @inheritDoc */
    public function register(string $name, string $ip, int $port, string|null $workerId = null, array $metadata = []): bool
    {
        $workerId = $workerId ?: '';
        try {
            $client = Redis::connection('plugin.workbunny.webman-push-server.server-register')->client();
            $res = $client->hSet($key = $this->_registrarKey($name, $ip, $port), $workerId, json_encode($metadata, JSON_UNESCAPED_UNICODE));
            // 如果存在定时间隔，则存在定时上报，则开启键值过期
            if ($interval = config('plugin.workbunny.webman-push-server.registrar.interval')) {
                $client->expire($key, $interval * 1.5);
            }
            return boolval($res);
        } catch (\RedisException $exception) {
            Log::channel('plugin.workbunny.webman-push-server.warning')
                ->warning("[REGISTRAR] Register failed. ", [
                    'method'  => __METHOD__,
                    'params'  => func_get_args(),
                    'message' => $exception->getMessage(),
                    'code'    => $exception->getCode(),
                ]);
            return false;
        }
    }

    /** @inheritDoc */
    public function report(string $name, string $ip, int $port, string|null $workerId = null, array $metadata = []): bool
    {
        $workerId = $workerId ?: '';
        try {
            $client = Redis::connection('plugin.workbunny.webman-push-server.server-register')->client();
            $res = $client->hSet($key = $this->_registrarKey($name, $ip, $port), $workerId, json_encode($metadata, JSON_UNESCAPED_UNICODE));
            // 如果存在定时间隔，则存在定时上报，则开启键值过期
            if ($interval = config('plugin.workbunny.webman-push-server.registrar.interval')) {
                $client->expire($key, $interval * 1.5);
            }
            return boolval($res);
        } catch (\RedisException $exception) {
            Log::channel('plugin.workbunny.webman-push-server.warning')
                ->warning("[REGISTRAR] Register failed. ", [
                    'method'  => __METHOD__,
                    'params'  => func_get_args(),
                    'message' => $exception->getMessage(),
                    'code'    => $exception->getCode(),
                ]);
            return false;
        }
    }

    /** @inheritDoc */
    public function unregister(string $name, string $ip, int $port, string|null $workerId = null): bool
    {
        $workerId = $workerId ?: '';
        try {
            $client = Redis::connection('plugin.workbunny.webman-push-server.server-register')->client();
            return boolval(
                $client->hDel($this->_registrarKey($name, $ip, $port), $workerId)
            );
        } catch (\RedisException $exception) {
            Log::channel('plugin.workbunny.webman-push-server.warning')
                ->warning("[REGISTRAR] Unregister failed. ", [
                    'method'  => __METHOD__,
                    'params'  => func_get_args(),
                    'message' => $exception->getMessage(),
                    'code'    => $exception->getCode(),
                ]);
            return false;
        }
    }

    /**
     * @param string|null $name
     * @param string|null $ip
     * @param int|null $port
     * @return string
     */
    protected function _registrarKey(null|string $name, null|string $ip, null|int $port): string
    {
        $name = $name === null ? '*' : $name;
        $ip = $ip === null ? '*' : $ip;
        $port = $port === null ? '*' : $port;
        return "registrar:$name:$ip:$port";
    }

    /**
     * @param string $registrarKey
     * @return string
     */
    protected function _getName(string $registrarKey): string
    {
        return explode(':', $registrarKey, 4)[1] ?? '';
    }

    /**
     * @param string $registrarKey
     * @return string
     */
    protected function _getIp(string $registrarKey): string
    {
        return explode(':', $registrarKey, 4)[2] ?? '';
    }

    /**
     * @param string $registrarKey
     * @return int|null
     */
    protected function _getPort(string $registrarKey): int|null
    {
        $port = explode(':', $registrarKey, 4)[3] ?? null;
        return $port === null ? null : intval($port);
    }
}