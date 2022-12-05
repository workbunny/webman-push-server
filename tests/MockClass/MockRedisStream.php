<?php
declare(strict_types=1);

namespace Tests\MockClass;

use function Workbunny\WebmanPushServer\uuid;

class MockRedisStream extends \Redis
{
    protected array $_streams = [];

    /**
     * @return array
     */
    public function getStreams(): array
    {
        return $this->_streams;
    }

    /**
     * @param array $streams
     */
    public function setStreams(array $streams): void
    {
        $this->_streams = $streams;
    }

    public function xLen($stream)
    {
        $stream = $this->_streams[$stream] ?? [];
        return count($stream);
    }

    public function xAdd($key, $id, $messages, $maxLen = 0, $isApproximate = false)
    {
        if($maxLen !== 0 and $this->xLen($key) >= $maxLen) {
            return false;
        }
        $this->_streams[$key][] = $messages;
        return true;
    }

    public function xDel($key, $ids)
    {
        foreach ($ids as $id) {
            unset($this->_streams[$key][$id]);
        }
    }

    public function xAck($stream, $group, $messages)
    {
        return true;
    }

}