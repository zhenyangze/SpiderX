<?php
/**
 * Short description for queue.php
 *
 * @package queue
 * @author zhenyangze <zhenyangze@gmail.com>
 * @version 0.1
 * @copyright (C) 2018 zhenyangze <zhenyangze@gmail.com>
 * @license MIT
 */
namespace SpiderX\Lib;
class Queue
{
    public function __construct($key = '')
    {
        if (empty($key)) {
            $key = 'YANGZE:' . md5(uniqid());
        }
        $this->key = $key;
        $this->redis = new \Redis();
        $this->redis->connect('127.0.0.1', 6379);
    }

    public function __destruct()
    {
        $this->redis->delete($this->key);
    }

    public function length()
    {
        return $this->redis->llen($this->key);
    }

    public function push($data)
    {
        $data = serialize($data);
        return $this->redis->lpush($this->key, $data);
    }

    public function pop()
    {
        $data = $this->redis->rpop($this->key);
        return unserialize($data);
    }
}
