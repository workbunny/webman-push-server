<?php declare(strict_types=1);
/**
 * @author workbunny/Chaz6chez
 * @email chaz6chez1993@outlook.com
 */
namespace Workbunny\WebmanPushServer\Registrars;

interface RegistrarInterface
{
    /**
     * 注册
     *
     * @param string $name 服务名称
     * @param string $ip 实例ip
     * @param int $port 实例port
     * @param string|null $workerId 进程id
     * @param array $metadata 元数据
     * @return bool
     */
    public function register(string $name, string $ip, int $port, string|null $workerId = null, array $metadata = []): bool;

    /**
     * 上报
     *
     * @param string $name 服务名称
     * @param string $ip 实例ip
     * @param int $port 实例port
     * @param string|null $workerId 进程id
     * @param array $metadata 元数据
     * @return bool
     */
    public function report(string $name, string $ip, int $port, string|null $workerId = null, array $metadata = []): bool;

    /**
     * 注销
     *
     * @param string $name 服务名称
     * @param string $ip 实例ip
     * @param int $port 实例port
     * @param string|null $workerId 进程id
     * @return bool
     */
    public function unregister(string $name, string $ip, int $port, string|null $workerId = null): bool;

    /**
     * 获取
     *
     * @param string|null $name
     * @return array|null
     */
    public function query(null|string $name): ?array;

    /**
     * 获取
     *
     * @param string $name
     * @param string $ip
     * @param int $port
     * @return array|null
     */
    public function get(string $name, string $ip, int $port): ?array;
}