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

/**
 * 
 */
class Unique
{
    /**
     * __construct 
     *
     * @param $config
     *
     * @return 
     */
    public function __construct($config = [])
    {
        $this->key = 'SpiderX:Unique' . $config['key'];
        $this->redis = new \Redis();
        $this->redis->connect($config['host'], $config['port']);
    }

    /**
     * getKey 
     *
     * @return 
     */
    public function getKey() {
        return $this->key;
    }

    /**
     * delete 
     *
     * @return 
     */
    public function delete()
    {
        $this->redis->del($this->key);
    }

    /**
     * length 
     *
     * @return 
     */
    public function length()
    {
        return $this->redis->scard($this->key);
    }

    /**
     * add 
     *
     * @param $value
     *
     * @return 
     */
    public function add($value)
    {
        return $this->redis->sadd($this->key, $value);
    }

    /**
     * remove 
     *
     * @param $key
     *
     * @return 
     */
    public function remove($key) {
        return $this->redis->sRem($this->key, $key);
    }
}
