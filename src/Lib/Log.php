<?php
/**
 * Short description for Log.php
 *
 * @package Log
 * @author zhenyangze <zhenyangze@gmail.com>
 * @version 0.1
 * @copyright (C) 2018 zhenyangze <zhenyangze@gmail.com>
 * @license MIT
 */

namespace SpiderX\Lib;

class Log
{
    public static $LOG_INFO = 'info';
    public static $LOG_ERROR = 'error';
    public static $LOG_DEBUG = 'debug';
    protected static $instance = [];
    public static function init()
    {
        if (!isset(self::$instance['climate'])) {
            self::$instance['climate'] = new \League\CLImate\CLImate;
        }
        if (!isset(self::$instance['logger'])) {
            self::$instance['logger'] = new \Katzgrau\KLogger\Logger(__DIR__ . '/../../logs');
        }
    }
    public static function out($info, $color = 'green')
    {
        self::init();
        self::$instance['climate']->$color($info);
    }

    public static function info($info = '')
    {
        self::init();
        self::$instance['logger']->info($info);
    }
    public static function error($info = '')
    {
        self::init();
        self::$instance['logger']->error($info);
    }
    public static function debug($info = '', $data = [])
    {
        self::init();
        self::$instance['logger']->debug($info, (array)$data);
    }
}
