<?php declare(strict_types=1);

use Webman\Config;

/**
 * 测试用config
 * @param string|null $key
 * @param mixed|null $default
 * @return mixed
 */
function config(string $key = null, mixed $default = null)
{
    if ($key === 'redis') {
        return [
            'default' => [
                'host'     => '172.17.0.1',//'redis',
                'password' => '',
                'port'     => 6379,
                'database' => 0,
            ],
        ];
    }
    return Config::get($key, $default);
}

/**
 * 测试用config path
 * @return string
 */
function config_path(): string
{
    return dirname(__DIR__) . '/src/config';
}

/**
 * 测试用runtime path
 * @return string
 */
function runtime_path(): string
{
    return __DIR__ . '/runtime';
}

/**
 * 测试用cpu count
 * @return int
 */
function cpu_count(): int
{
    // Windows does not support the number of processes setting.
    if (DIRECTORY_SEPARATOR === '\\') {
        return 1;
    }
    $count = 4;
    if (is_callable('shell_exec')) {
        if (strtolower(PHP_OS) === 'darwin') {
            $count = (int)shell_exec('sysctl -n machdep.cpu.core_count');
        } else {
            $count = (int)shell_exec('nproc');
        }
    }
    return $count > 0 ? $count : 4;
}
