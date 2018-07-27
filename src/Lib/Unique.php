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
        $this->deleKey();
    }
    public function deleKey()
    {
        $this->redis->delete($this->key);
    }

    public function add($value)
    {
        return $this->redis->sadd($this->key, $value);
    }
}
