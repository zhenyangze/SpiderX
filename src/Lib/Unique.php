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
        $this->key = 'SpiderX:' . $config['key'] . ':Queue';
        $this->redis = new \Redis();
        $this->redis->connect($config['host'], $config['port']);
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
}
