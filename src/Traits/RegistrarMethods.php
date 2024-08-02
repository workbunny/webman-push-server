<?php declare(strict_types=1);
/**
 * @author workbunny/Chaz6chez
 * @email chaz6chez1993@outlook.com
 */

namespace Workbunny\WebmanPushServer\Traits;

use InvalidArgumentException;
use Workbunny\WebmanPushServer\Registrars\RegistrarInterface;
use Workerman\Timer;
use Workerman\Worker;
use function call_user_func;
use function count;
use function function_exists;

/**
 * 简单的参数验证工具
 */
trait RegistrarMethods
{
    protected ?int $registrarTimerId = null;

    /**
     * @return RegistrarInterface|null
     */
    public function registrarGet(): ?RegistrarInterface
    {
        $registrar = config('plugin.workbunny.webman-push-server.registrar.handler');
        return $registrar instanceof RegistrarInterface ? $registrar : null;
    }

    /**
     * @param Worker $worker
     * @return void
     */
    public function registrarStart(Worker $worker): void
    {
        if (
            $registrar = $this->registrarGet() and
            $serverName = static::getServerName() and
            $ip = $this->registrarGetHostIp() and
            $port = $this->registrarGetHostPort()
        ) {
            // 注册
            $registrar->register($serverName, $ip, $port, $id = ($worker->id === 0 ? 'master' : strval($worker->id)));
            // 定时上报
            if ($interval = config('plugin.workbunny.webman-push-server.registrar.interval')) {
                $this->registrarTimerId = Timer::add($interval, function () use ($registrar, $serverName, $ip, $port, $id) {
                    $res = $registrar->get($serverName, $ip, $port);
                    $metadata = $res['id'] ?? [];
                    $registrar->report($serverName, $ip, $port, $id, $metadata);
                });
            }
        }
    }

    /**
     * @param Worker $worker
     * @return void
     */
    public function registrarStop(Worker $worker): void
    {
        if ($this->registrarTimerId) {
            Timer::del($this->registrarTimerId);
        }
        if (
            $registrar = $this->registrarGet() and
            $serverName = static::getServerName() and
            $ip = $this->registrarGetHostIp() and
            $port = $this->registrarGetHostPort()
        ) {
            $registrar->unregister($serverName, $ip, $port, ($worker->id === 0 ? 'master' : strval($worker->id)));
        }
    }

    /**
     * @return string
     */
    public function registrarGetHostIp(): string
    {
        return trim(shell_exec('curl -s ifconfig.me'));
    }

    /**
     * @return int|null
     */
    public function registrarGetHostPort(): ?int
    {
        $serverName = static::getServerName();
        return config("plugin.workbunny.webman-push-server.app.$serverName.port");
    }

    /**
     * @return string
     */
    abstract public static function getServerName(): string;
}
