<?php
/**
 * Short description for table.php
 *
 * @package table
 * @author zhenyangze <zhenyangze@gmail.com>
 * @version 0.1
 * @copyright (C) 2018 zhenyangze <zhenyangze@gmail.com>
 * @license MIT
 */
namespace SpiderX\Lib;

class Table
{
    public function __construct($config = [])
    {
        $this->hashKey = 'SpiderX:Table:Hash:' . $config['key'];
        $this->setKey = 'SpiderX:Table:Set:' . $config['key'];
        $this->redis = new \Redis();
        $this->redis->connect($config['host'], $config['port']);
    }

    public function getHashKey() {
        return $this->hashKey;
    }

    public function getSetKey () {
        return $this->setKey;
    }

    public function delete()
    {
        $this->redis->delete($this->setKey);

        $keys = $this->redis->keys($this->hashKey . '*');
        $this->redis->delete($keys);
    }

    public function length()
    {
        return $this->redis->ssize($this->setKey);
    }

    public function set($key, $value)
    {
        $hashKey = $this->hashKey . $key;
        $this->redis->sadd($this->setKey, $hashKey);
        $this->redis->set($hashKey, serialize($value), 3600 * 24 * 7);
    }

    public function get($key)
    {
        $hashKey = $this->hashKey . $key;
        $data = $this->redis->get($hashKey);
        if ($data) {
            return unserialize($data);
        }
        return;
    }

    public function remove($key)
    {
        $hashKey = $this->hashKey . $key;
        $this->redis->delete($hashKey);
        $this->redis->sRemove($hashKey);
    }
}
