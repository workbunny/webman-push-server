<?php declare(strict_types=1);
/**
 * @author workbunny/Chaz6chez
 * @email chaz6chez1993@outlook.com
 */
namespace Workbunny\WebmanPushServer\Channels;

interface ChannelInterface
{

    /**
     * 关闭订阅
     *
     * @return mixed
     */
    public function unsubscribe(): mixed;

    /**
     * 订阅频道
     *
     * @param string $channels
     * @param callable|\Closure|array $cb
     * @return mixed
     */
    public function subscribe(string $channels, callable|\Closure|array $cb): mixed;

    /**
     * 发送消息
     *
     * @param string $channel
     * @param string $message
     * @return bool|int
     */
    public function publish(string $channel, string $message): bool|int;

}