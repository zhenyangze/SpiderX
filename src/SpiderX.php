<?php
/**
 * Short description for spider.php
 *
 * @package spider
 * @author zhenyangze <zhenyangze@gmail.com>
 * @version 0.1
 * @copyright (C) 2018 zhenyangze <zhenyangze@gmail.com>
 * @license MIT
 */
namespace SpiderX;

use SpiderX\Lib\Event;
use SpiderX\Lib\Log;
use SpiderX\Lib\Queue;
use SpiderX\Lib\SpiderXAbstract;
use SpiderX\Lib\Unique;
use SpiderX\Lib\Url;

class SpiderX extends SpiderXAbstract
{
    public function __construct($config = [])
    {
        $this->config = $config;
        $this->checkConfig();
        $this->init();
        $this->checkData();
        $this->addListener();
    }

    protected function checkConfig()
    {
        if (empty($this->config['name'])) {
            Log::out('配置信息name字段不存在', 'red');
            exit;
        }
        if (empty($this->config['redis'])) {
            $this->config['redis'] = [
                'host' => '127.0.0.1',
                'port' => 6379,
            ];
        }
        $this->config['rule'] = array_column($this->config['rule'], null, 'name');
    }

    protected function checkData() {

        Log::info('Queue key: ' . $this->queue->getKey());
        Log::info('uniqueArray key: ' . $this->uniqueArray->getKey());

        if ($this->queue->length() > 0 || $this->uniqueArray->length() > 0) {
            $ret = '';
            do {
                $ret = Log::read('是否继续上次的任务[y/n]');
            } while (!in_array($ret, ['y', 'n']));

            if ($ret == 'n') {
                $this->queue->delete();
                $this->uniqueArray->delete();
            }
        }
    }

    protected function init()
    {
        $redisConfig = $this->config['redis'];
        $redisConfig['key'] = $this->config['name'];
        $this->queue = new Queue($redisConfig);
        $this->uniqueArray = new Unique($redisConfig);

        if (!isset($this->setGetHtml)) {
            $this->setGetHtml = function ($pageInfo) {
                return Url::getHtml($pageInfo);
            };
        }
        if (!isset($this->setGetLinks)) {
            $this->setGetLinks = function ($html) {
                return Url::getLinks($html);
            };
        }
    }

    protected function addListener()
    {
        Event::listen('on_start', function () {
            Log::info('SpiderX start');
        });
        Event::listen('on_finish', function () {
            Log::info('SpiderX finish');
        });

        Event::listen('on_loadding', function ($args) {
            Log::info('loading page: ' . $args[0]['url']);
        });
        Event::listen('on_loaded', function ($args) {
            Log::info('loaded page: ' . $args[0]['url']);
        });

        if (isset($this->config['debug']) && $this->config['debug'] == true) {
            Event::listen('on_fetch', function ($args) {
                Log::debug('fetch_data: ' . $args[0]['url'], $args[2]);
            });

            Event::listen('on_add_url', function ($args) {
                Log::debug('add page: ', $args);
            });
            Event::listen('on_add_url_fail', function ($args) {
                Log::debug('add fail', $args);
            });
            Event::listen('on_retry_page', function ($args) {
                Log::debug('retry page', $args);
            });

        } else {
            Event::listen('on_add_url', function ($args) {
                Log::info('add page: ' . $args[0]['url']);
            });
            Event::listen('on_retry_page', function ($args) {
                Log::info('retry page: ' . $args[0]['url']);
            });
        }
    }

    public function start() {
        // 执行前可以添加数据
        $this->invok('on_start');

        $taskNum = function_exists('\swoole_cpu_num') ? \swoole_cpu_num() * 2 : 1;
        $taskNum = isset($this->config['tasknum']) ? $this->config['tasknum'] : $taskNum;

        if (class_exists('\swoole_process') && $taskNum > 1) {
            $task = $this;
            $taskList = [];
            Log::info('开启多进程, nums: ' . $taskNum);
            for($i = 0; $i < $taskNum; $i++) {
                $process = new \swoole_process(function(\swoole_process $worker) use($i, $task, $taskList){
                    $task->runTask();
                });
                $taskList[$i] = $process->start();
            }
            \swoole_process::wait();
        } else {
            $this->runTask();
        }
        $this->invok('on_finish');
    }

    protected function runTask()
    {
        $this->init();

        // 添加首页
        $this->addStartUrl();

        while ($this->queue->length()) {
            $pageInfo = $this->queue->pop();

            // 加载前校验
            if (false === $this->invok('on_loadding_' . $pageInfo['name'], [
                $pageInfo
            ], true)) {
                continue;
            }

            $html = $this->setGetHtml($pageInfo);

            // 加载后校验
            if (false === $this->invok('on_loaded_' . $pageInfo['name'], [
                $pageInfo,
                $html,
            ], true)) {
                continue;
            }

            if (empty($html) && $pageInfo['retry'] < 2) {
                if (false === $this->invok('on_retry_page', [
                    $pageInfo
                ])) {
                    continue;
                }
                $this->addUrl($pageInfo);
                continue;
            }

            //列表和详情共用一套
            $data = $this->fetchData($pageInfo, $html);

            // 处理采集数据
            $userData = $this->invok('on_fetch_' . $pageInfo['name'], [
                $pageInfo,
                $html,
                $data
            ]);

            $data = empty($userData) ? $data : $userData;

            // 检测子链
            $this->fetchLinks($pageInfo, $html, $data);
        }
    }

    protected function addStartUrl()
    {
        if (!empty($this->config['start'])) {
            array_walk($this->config['start'], function ($url) {
                $this->addUrl([
                    'url' => $url,
                    'type' => 'start',
                    'name' => 'start',
                    'context' => [],
                ]);
            });
        }
    }
}
