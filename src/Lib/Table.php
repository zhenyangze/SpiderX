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

/**
 * 
 */
class Table
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
        $this->hashKey = 'SpiderX:Table:Hash:' . $config['key'];
        $this->setKey = 'SpiderX:Table:Set:' . $config['key'];
        $this->redis = new \Redis();
        $this->redis->connect($config['host'], $config['port']);
    }

    /**
     * getHashKey 
     *
     * @return 
     */
    public function getHashKey() {
        return $this->hashKey;
    }

    /**
     * getSetKey 
     *
     * @return 
     */
    public function getSetKey () {
        return $this->setKey;
    }

    /**
     * delete 
     *
     * @return 
     */
    public function delete()
    {
        $this->redis->del($this->setKey);

        $keys = $this->redis->keys($this->hashKey . '*');
        $this->redis->del($keys);
    }

    /**
     * length 
     *
     * @return 
     */
    public function length()
    {
        return $this->redis->scard($this->setKey);
    }

    /**
     * set 
     *
     * @param $key
     * @param $value
     *
     * @return 
     */
    public function set($key, $value)
    {
        $hashKey = $this->hashKey . $key;
        $this->redis->sadd($this->setKey, $hashKey);
        $this->redis->set($hashKey, serialize($value), 3600 * 24 * 7);
    }

    /**
     * get 
     *
     * @param $key
     *
     * @return 
     */
    public function get($key)
    {
        $hashKey = $this->hashKey . $key;
        $data = $this->redis->get($hashKey);
        if ($data) {
            return unserialize($data);
        }
        return;
    }

    /**
     * remove 
     *
     * @param $key
     *
     * @return 
     */
    public function remove($key)
    {
        $hashKey = $this->hashKey . $key;
        $this->redis->del($hashKey);
        $this->redis->sRem($hashKey);
    }
    }
