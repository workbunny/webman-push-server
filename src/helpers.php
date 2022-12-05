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

use support\Response;
use Webman\Config;

if (!function_exists('response')) {
    /**
     * @param int $httpStatus
     * @param array|string $data
     * @param array $header
     * @return Response
     */
    function response(int $httpStatus, $data, array $header = []): Response
    {
        return new Response($httpStatus, array_merge([
            'Content-Type' => 'application/json',
            'Server'       => 'workbunny'
        ], $header), is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : $data);
    }
}

if (!function_exists('config')){
    /**
     * @param string|null $key
     * @param $default
     * @return array|mixed|null
     */
    function config(string $key = null, $default = null)
    {
        Config::load(__DIR__ . '/config', ['route']);
        return Config::get($key, $default);
    }
}

/**
 * 生成UUID
 */
if (!function_exists('uuid')) {
    /**
     * uuid
     * @return string
     */
    function uuid(): string
    {
        if (function_exists('uuid_create')) {
            return uuid_create(1);
        }
        return fuuid();
    }
}

/**
 * 模拟生成UUID
 */
if (!function_exists('fuuid')) {
    /**
     * fake uuid
     * @return string
     */
    function fuuid(): string
    {
        $chars = md5(uniqid((string)mt_rand(), true));
        $uuid  = substr($chars, 0, 8) . '-';
        $uuid  .= substr($chars, 8, 4) . '-';
        $uuid  .= substr($chars, 12, 4) . '-';
        $uuid  .= substr($chars, 16, 4) . '-';
        $uuid  .= substr($chars, 20, 12);
        return $uuid;
    }
}
