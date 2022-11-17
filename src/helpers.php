<?php
/**
 * This file is part of workbunny.
 *
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    chaz6chez<250220719@qq.com>
 * @copyright chaz6chez<250220719@qq.com>
 * @link      https://github.com/workbunny/webman-multi-push
 * @license   https://github.com/workbunny/webman-multi-push/blob/main/LICENSE
 */
declare(strict_types=1);

namespace Workbunny\WebmanPushServer;


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
