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

use Inhere\Console\IO\Input;
use Inhere\Console\IO\Output;
use SpiderX\Lib\Event;
use SpiderX\Lib\Log;
use SpiderX\Lib\Queue;
use SpiderX\Lib\SpiderXAbstract;
use SpiderX\Lib\Table;
use SpiderX\Lib\Unique;
use SpiderX\Lib\Url;
use SpiderX\Lib\Util;
use SpiderX\Lib\Worker;
use Inhere\Console\Utils\Show;

/**
 *
 */
class SpiderX extends SpiderXAbstract
{

    protected $lockFile = '';
    /**
     * __construct
     *
     * @param $config
     *
     * @return
     */
    public function __construct($config = [])
    {
        $this->config = $config;
        $this->checkConfig();
        $this->init();
   }

    /**
     * checkConfig
     *
     * @return
     */
    protected function checkConfig()
    {
        $this->config['start_time'] = time();
        $this->config['tasknum'] = isset($this->config['tasknum']) && $this->config['tasknum'] > 1 ? $this->config['tasknum'] : 1;
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

        // 检查PHP版本
        if (version_compare(PHP_VERSION, '5.3.0', 'lt')) {
            Log::out('PHP 5.3+ is required, currently installed version is: ' . phpversion(), 'red');
            exit;
        }

        // 多任务需要pcntl扩展支持
        if (!function_exists('pcntl_fork')) {
            Log::out("Multitasking needs pcntl, the pcntl extension was not found", 'red');
            exit;
        }

        // 守护进程需要pcntl扩展支持
        if (!function_exists('pcntl_fork')) {
            Log::out("Daemonize needs pcntl, the pcntl extension was not found", 'red');
            exit;
        }
        
        $this->lockFile = sys_get_temp_dir() . md5($this->config['name'] . __FILE__);
    }

    /**
     * checkData
     *
     * @return
     */
    protected function checkData()
    {

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

        $this->runningQueue->delete();
        $this->taskTable->delete();

        $this->config['ori_total_data'] = $this->uniqueArray->length();
        $this->config['ori_left_data'] = $this->queue->length();
    }

    /**
     * registerCommand 
     *
     * @return null 
     */
    protected function registerCommand() {
        $task = $this;
        $meta = [
            'name' => 'php SpiderX',
            'version' => '1.0.0',
        ];
        $input = new Input;
        $output = new Output;
        $app = new \Inhere\Console\Application($meta, $input, $output);
        $app->command('run', function (Input $in, Output $out) use ($task) {
            $task->config['modal'] = 'report';
            $task->prepare();
            $task->startTask();
        }, 'run script with report');
        $app->command('daemon', function (Input $in, Output $out) use ($task) {
            $task->config['modal'] = 'daemon';
            $task->prepare();
            $task->startTask();
        }, 'run script in daemon modal');
        $app->command('debug', function (Input $in, Output $out) use ($task) {
            $task->config['modal'] = 'debug';
            $task->prepare();
            $task->startTask();
        }, 'run script in debug modal');
        $app->command('status', function (Input $in, Output $out) use ($task) {
            $out->info('LockFile: ' . $task->lockFile);
            $contents = @file_get_contents($task->lockFile);
            if (empty($contents)) {
                $out->info('no task');
                return;
            }
            $lineList = explode("\n", $contents);
            foreach($lineList as $line) {
                if (empty($line)) {
                    continue;
                }
                $taskInfo = @json_decode($line, true);
                $isRun = posix_getpgid($taskInfo['pid']);
                $str = sprintf('[%d]Pid:%d Status:%s', $taskInfo['taskid'], $taskInfo['pid'], $isRun ? 'Running' : 'Stopped');
                $out->info($str);
            }
        }, 'show the SpiderX status');
        $app->command('stop', function (Input $in, Output $out) use ($task) {
            $task->stopTask();
            $out->info('Stopped SpiderX');
        }, 'stop all the spiderX Process');
        $app->run();
    }

    /**
     * init
     *
     * @return
     */
    protected function init()
    {
        $redisConfig = $this->config['redis'];
        $redisConfig['key'] = $this->config['name'];
        $this->queue = new Queue($redisConfig);
        $this->uniqueArray = new Unique($redisConfig);

        $this->taskTable = new Table(array_merge($redisConfig, [
            'key' => $this->config['name'] . 'Task'
        ]));
        $this->runningQueue = new Unique(array_merge($redisConfig, [
            'key' => $this->config['name'] . 'Running'
        ]));

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

    /**
     * addListener
     *
     * @return
     */
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

    /**
     * prepare 
     *
     * @return 
     */
    protected function prepare() {
        $this->checkData();
        $this->addListener();
    }

    /**
     * start 
     *
     * @return 
     */
    public function start() {
        $this->registerCommand();
    }

    /**
     * start
     *
     * @return
     */
    public function startTask()
    {
        if (file_exists($this->lockFile)) {
            @unlink($this->lockFile);
            @touch($this->lockFile);
        }
 
        if ($this->config['modal'] == 'daemon') {
            Util::daemonize();
        }
        // 执行前可以添加数据
        $this->invok('on_start');
        $this->addStartUrl();

        if ($this->config['modal'] == 'debug') {
            $this->startDebug();
        } else {
            $this->startProcess();
        }

        $this->invok('on_finish');
    }

    /**
     * startDebug
     *
     * @return
     */
    protected function startDebug()
    {
        $this->runTask();
    }

    /**
     * startProcess
     *
     * @return
     */
    protected function startProcess()
    {
        $task = $this;

        $worker = new Worker();
        $worker->run_once = isset($this->config['run_once']) ? $this->config['run_once'] : true;
        $worker->count = $this->config['tasknum'] + 1;
        $worker->on_worker_start = function ($childWorker) use ($task) {
            $task->init();

            $task->registerTask($childWorker);

            if ($childWorker->worker_id == 1 && $this->config['modal'] == 'report') {
                $task->report();
            } else {
                $task->runTask();
            }

            $task->unRegisterTask($childWorker);
        };
        $worker->run();
    }

    /**
     * report
     *
     * @return null
     */
    protected function report()
    {
        if ($this->config['modal'] == 'daemon') {
            return;
        }

        $allStop = false;
        while (!$allStop) {
            $arr = array(27, 91, 72, 27, 91, 50, 74);
            foreach ($arr as $a) {
                print chr($a);
            }
            $totalNum = $this->uniqueArray->length();
            $leftNum = $this->queue->length();

            $data = [
                'spider    name' => $this->config['name'],
                'spider version' => '1.0.0',
                'php    version' => phpversion(),
                ' ',
                'start time' => date('Y-m-d H:i:s', $this->config['start_time']),
                'run   time' => Util::time2second(time() - $this->config['start_time']),
                ' ',
                'task  num' => $this->config['tasknum'],
                'queue num' => $totalNum,
                'left  num' => $leftNum,
                'percentum (%)' => (100 - Util::getPercent($leftNum, $totalNum)),
                'speed num (s)' => round((($totalNum - $this->config['ori_total_data']) - ($leftNum - $this->config['ori_left_data'])) / (time() - $this->config['start_time']), 2),
            ];

            Show::panel($data, 'Spider Info', [
                'borderChar' => '-'
            ]);

            $allStop = true;
            for ($i = 2; $i < $this->config['tasknum'] + 1; $i++) {
                if ($this->checkIsRun($i)) {
                    $allStop = false;
                    break;
                }
            }
            sleep(1);
            if ($this->checkShouldExit()) {
                break;
            }
        }
    }

    /**
     * runTask
     *
     * @return null
     */
    protected function runTask()
    {
        while ($this->queue->length() || $this->runningQueue->length()) {
            $pageInfo = $this->queue->pop();

            if (empty($pageInfo)) {
                sleep(1);
                continue;
            }

            $this->runningQueue->add(md5(serialize($pageInfo)));

            // 加载前校验
            if (false === $this->invok('on_loadding_' . $pageInfo['name'], [
                $pageInfo
            ], true)) {
            $this->runningQueue->remove(md5(serialize($pageInfo)));
                continue;
            }

            $html = $this->setGetHtml($pageInfo);

            // 加载后校验
            if (false === $this->invok('on_loaded_' . $pageInfo['name'], [
                $pageInfo,
                $html,
            ], true)) {
                $this->runningQueue->remove(md5(serialize($pageInfo)));
                continue;
            }

            if (empty($html) && $pageInfo['retry'] < 2) {
                if (false === $this->invok('on_retry_page', [
                    $pageInfo
                ])) {
                    $this->runningQueue->remove(md5(serialize($pageInfo)));
                    continue;
                }
                $this->addUrl($pageInfo);
                $this->runningQueue->remove(md5(serialize($pageInfo)));
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

            $this->runningQueue->remove(md5(serialize($pageInfo)));

            // 检测是否要退出
            if ($this->checkShouldExit()) {
                break;
            }
            //$this->report();
        }
    }

    /**
     * addStartUrl
     *
     * @return null
     */
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

    /**
     * registerTask
     *
     * @param $childWorker
     *
     * @return null
     */
    protected function registerTask($childWorker)
    {
        @file_put_contents($this->lockFile, json_encode([
            'taskid' => $childWorker->worker_id,
            'pid' => $childWorker->worker_pid,
        ]) . "\n", FILE_APPEND | LOCK_EX);
        $this->taskTable->set($childWorker->worker_id, [
            'taskid' => $childWorker->worker_id,
            'pid' => $childWorker->worker_pid,
            'mem' => Util::memoryGetUsage(),
        ]);
    }

    /**
     * unRegisterTask
     *
     * @param $childWorker
     *
     * @return null
     */
    protected function unRegisterTask($childWorker)
    {
        $this->taskTable->remove($childWorker->worker_id);
    }

    /**
     * checkIsRun
     *
     * @param $workerId
     *
     * @return int
     */
    protected function checkIsRun($workerId)
    {
        $workInfo = $this->taskTable->get($workerId);
        $pid = $workInfo['pid'];
        if (empty($pid)) {
            return false;
        }
        return posix_getpgid($pid);
    }

    /**
     * stopTask
     *
     * @param $workerId
     *
     * @return int
     */
    protected function stopTask()
    {
        @file_put_contents($this->lockFile, 0);
    }

    protected function checkShouldExit() {
        return  '0' === file_get_contents($this->lockFile);
    }
}
