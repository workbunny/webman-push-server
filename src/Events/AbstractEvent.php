<?php
declare(strict_types=1);

namespace Workbunny\WebmanPushServer\Events;

use Workbunny\WebmanPushServer\Server;
use Workerman\Connection\TcpConnection;
use const Workbunny\WebmanPushServer\EVENT_PING;
use const Workbunny\WebmanPushServer\EVENT_SUBSCRIBE;
use const Workbunny\WebmanPushServer\EVENT_UNSUBSCRIBE;


abstract class AbstractEvent
{
    const CLIENT_EVENT = 'client_event';
    const SERVER_EVENT = 'server_event';

    /** @var string  */
    protected string $_event;

    /**
     * @var string[]
     */
    protected static array $_events = [
        EVENT_PING          => Ping::class,
        EVENT_SUBSCRIBE     => Subscribe::class,
        EVENT_UNSUBSCRIBE   => Unsubscribe::class,
        self::CLIENT_EVENT  => ClientEvent::class,
        self::SERVER_EVENT  => ServerEvent::class
    ];

    /**
     * @var AbstractEvent[]
     */
    protected static array $_eventObj = [];

    /**
     * @param string $event
     */
    public function __construct(string $event)
    {
        $this->_event = $event;
    }

    /**
     * @return string
     */
    public function getEvent(): string
    {
        return $this->_event;
    }

    /**
     * @param string $event
     * @return AbstractEvent|null
     */
    public static function factory(string $event): ?AbstractEvent
    {
        if(self::exists($preEvent = self::pre($event))){
            return self::$_eventObj[$preEvent] ?? (self::$_eventObj[$preEvent] = new $preEvent($event));
        }
        return null;
    }

    /**
     * @param string $event
     * @param string $eventClass
     * @return void
     */
    final public static function register(string $event, string $eventClass): void
    {
        if(self::exists($event)){
            throw new \InvalidArgumentException("Event $event already exists. ");
        }
        if(!(new $eventClass) instanceof AbstractEvent){
            throw new \InvalidArgumentException("Invalid event class $eventClass. ");
        }
        self::$_events[$event] = $eventClass;
    }

    /**
     * @param string $event
     * @return bool
     */
    final public static function exists(string $event): bool
    {
        return isset(self::$_events[$event]);
    }

    /**
     * 预处理
     * @param string $event
     * @return string|null
     */
    public static function pre(string $event): ?string
    {
        if (strpos($event, 'pusher:') === 0) {
            return self::CLIENT_EVENT;
        }
        if (strpos($event, 'pusher_internal:') === 0) {
            return self::SERVER_EVENT;
        }
        return null;
    }

    /**
     * 响应
     * @param Server $pushServer
     * @param TcpConnection $connection
     * @param array $request
     * @return void
     */
    abstract public function response(Server $pushServer, TcpConnection $connection, array $request): void;
}