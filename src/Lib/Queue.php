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
    public function __construct($config = [])
    {
        $this->key = 'SpiderX:Queue:' . $config['key'];
        $this->redis = new \Redis();
        $this->redis->connect($config['host'], $config['port']);
    }

    public function getKey() {
        return $this->key;
    }

    public function delete()
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
