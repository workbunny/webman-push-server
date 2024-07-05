<?php declare(strict_types=1);
/**
 * @author workbunny/Chaz6chez
 * @email chaz6chez1993@outlook.com
 */
namespace Workbunny\WebmanPushServer\Traits;

use Workerman\Connection\TcpConnection;
use function Workbunny\WebmanPushServer\str2byte;
use function Workbunny\WebmanPushServer\uuid;

trait ConnectionsMethods
{
    /** @var int 统计间隔 */
    protected static int $_statisticsInterval = 0;

    /**
     * 当前进程所有连接
     *
     * @var TcpConnection[][] = [
     *  'appKey_1' => [
     *      'socketId_1' => TcpConnection_1, @see self::getConnectionProperty()
     *      'socketId_2' => TcpConnection_2, @see self::getConnectionProperty()
     *  ],
     * ]
     */
    protected static array $_connections = [];

    /**
     * 获取统计间隔
     *
     * @return int
     */
    public static function getStatisticsInterval(): int
    {
        return self::$_statisticsInterval;
    }

    /**
     * 设置统计间隔
     *
     * @param int $statisticsInterval
     * @return void
     */
    public static function setStatisticsInterval(int $statisticsInterval): void
    {
        self::$_statisticsInterval = $statisticsInterval;
    }

    /**
     * 设置连接信息
     *
     * @param TcpConnection $connection
     * @param string $property =
     * clientNotSendPingCount (int) |
     * appKey (string) |
     * queryString (string) |
     * socketId (string) |
     * channels = [ channel => ''|uid] |
     * sendLastTime (int) |
     * recvLastTime (int) |
     * sendBytesStatistics (int) |
     * recvBytesStatistics (int)
     * @param mixed|null $value
     * @return void
     */
    public static function setConnectionProperty(TcpConnection $connection, string $property, mixed $value): void
    {
        $connection->$property = $value;
    }

    /**
     * 获取连接信息
     *
     * @param TcpConnection $connection
     * @param string $property =
     *  clientNotSendPingCount (int) |
     *  appKey (string) |
     *  queryString (string) |
     *  socketId (string) |
     *  channels = [ channel => ''|uid] |
     *  sendLastTime (int) |
     *  recvLastTime (int) |
     *  sendBytesStatistics (int) |
     *  recvBytesStatistics (int)
     * @param mixed|null $default
     * @return mixed|null
     */
    public static function getConnectionProperty(TcpConnection $connection, string $property, mixed $default = null): mixed
    {
        return $connection->$property ?? $default;
    }

    /**
     * 设置连接发送每秒字节数
     *
     * @param TcpConnection $connection
     * @param string $buffer
     * @return void
     */
    public static function setSendBytesStatistics(TcpConnection $connection, string $buffer): void
    {
        $bps = static::getSendBytesStatistics($connection);
        if ($bps !== false) {
            if ($bps === null) {
                static::setConnectionProperty($connection, 'sendLastTime',
                    time()
                );
            }
            static::setConnectionProperty($connection, 'sendBytesStatistics',
                intval($bps) + str2byte($buffer)
            );
        }
    }

    /**
     * 获取连接发送每秒字节数
     *
     * @param TcpConnection $connection
     * @return false|int|null null表示已经到下一个时间周期 false表示未开启统计
     */
    public static function getSendBytesStatistics(TcpConnection $connection): null|false|int
    {
        if (($interval = static::getStatisticsInterval()) > 0) {
            if (static::getConnectionProperty($connection, 'sendLastTime',0) + $interval >= time()) {
                return static::getConnectionProperty($connection, 'sendBytesStatistics', 0);
            }
        }
        return null;
    }

    /**
     * 设置连接接收每秒字节数
     *
     * @param TcpConnection $connection
     * @param string $buffer
     * @return void
     */
    public static function setRecvBytesStatistics(TcpConnection $connection, string $buffer): void
    {
        $bps = static::getRecvBytesStatistics($connection);
        if ($bps !== false) {
            if ($bps === null) {
                static::setConnectionProperty($connection, 'recvLastTime',
                    time()
                );
            }
            static::setConnectionProperty($connection, 'recvBytesStatistics',
                intval($bps) + str2byte($buffer)
            );
        }
    }

    /**
     * 获取连接接收每秒字节数
     *
     * @param TcpConnection $connection
     * @return false|int|null null表示已经到下一个时间周期 false表示未开启统计
     */
    public static function getRecvBytesStatistics(TcpConnection $connection): null|false|int
    {
        if (($interval = static::getStatisticsInterval()) > 0) {
            if (static::getConnectionProperty($connection, 'recvLastTime',0) + $interval >= time()) {
                return static::getConnectionProperty($connection, 'recvBytesStatistics', 0);
            }
        }
        return null;
    }

    /**
     * 创建一个全局的客户端id
     *
     * @return string
     */
    public static function createSocketId(): string
    {
        return uuid();
    }

    /**
     * @return TcpConnection[][]
     */
    public static function getConnections(): array
    {
        return static::$_connections;
    }

    /**
     * @param array $connections
     * @return void
     */
    public static function setConnections(array $connections): void
    {
        static::$_connections = $connections;
    }

    /**
     * 设置连接
     *
     * @param string $appKey
     * @param string $socketId
     * @param TcpConnection $connection
     * @return void
     */
    public static function setConnection(string $appKey, string $socketId, TcpConnection $connection): void
    {
        static::$_connections[$appKey][$socketId] = $connection;
    }

    /**
     * 获取连接
     *
     * @param string $appKey
     * @param string $socketId
     * @return TcpConnection|null
     */
    public static function getConnection(string $appKey, string $socketId): ?TcpConnection
    {
        return static::$_connections[$appKey][$socketId] ?? null;
    }

    /**
     * 移除连接
     *
     * @param string $appKey
     * @param string $socketId
     * @return void
     */
    public static function unsetConnection(string $appKey, string $socketId): void
    {
        // 移除connections
        unset(static::$_connections[$appKey][$socketId]);
    }
}