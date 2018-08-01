<?php
/**
 * Short description for array.php
 *
 * @package array
 * @author zhenyangze <zhenyangze@gmail.com>
 * @version 0.1
 * @copyright (C) 2018 zhenyangze <zhenyangze@gmail.com>
 * @license MIT
 */
namespace SpiderX\Lib;

class Unique
{
    public function __construct($config = [])
    {
        $this->key = 'SpiderX:Unique' . $config['key'];
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
        return $this->redis->ssize($this->key);
    }

    public function add($value)
    {
        return $this->redis->sadd($this->key, $value);
    }

    public function remove($key) {
        return $this->redis->sRem($this->key, $key);
    }
}
