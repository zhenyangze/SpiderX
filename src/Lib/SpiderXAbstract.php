<?php
/**
 * Short description for SpiderXAbstract.php
 *
 * @package SpiderXAbstract
 * @author zhenyangze <zhenyangze@gmail.com>
 * @version 0.1
 * @copyright (C) 2018 zhenyangze <zhenyangze@gmail.com>
 * @license MIT
 */
namespace SpiderX\Lib;

/**
 * SpiderXAbstract
 */
abstract class SpiderXAbstract
{
    /**
     * config
     */
    public $config;
    /**
     * 数据队列
     */
    protected $queue;
    /**
     * 用于检测重复
     */
    protected $uniqueArray;

    /**
     * 任务表
     */
    protected $taskTable;

    /**
     * start
     *
     * @return
     */
    abstract public function start();

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
    }

    /**
     * __call
     *
     * @param $method
     * @param $args
     *
     * @return
     */
    public function __call($method, $args)
    {
        if (isset($this->$method)) {
            return call_user_func_array($this->$method, $args);
        }
    }

    /**
     * invok
     *
     * @param $func
     * @param $args
     * @param $default
     *
     * @return
     */
    protected function invok($func, $args = [], $default = [])
    {
        $eventName = $func;
        if (stristr($eventName, 'on_load') || stristr($eventName, 'on_fetch')) {
            $eventName = str_ireplace(strrchr($eventName, '_'), '', $eventName);
        }
        
        Event::trigger($eventName, $args);

        if (isset($this->$func)) {
            return call_user_func_array($this->$func, $args);
        }
    }


    /**
     * addUrl
     *
     * @param $pageInfo
     *
     * @return
     */
    public function addUrl($pageInfo = [])
    {
        if (empty($pageInfo['name'])) {
            Log::out('配置信息name字段不存在', 'red');
            return;
        }
        if (empty($pageInfo['type'])) {
            Log::out('配置信息type字段不存在', 'red');
            return;
        }
        if (false === $this->invok('on_add_url', [
            $pageInfo
        ])) {
            return;
        };

        if (!isset($pageInfo['context'])) {
            $pageInfo['context'] = [];
        }

        if (isset($this->config['cookie'])) {
            $pageInfo['cookie'] = $this->config['cookie'];
        }
        if (isset($this->config['timeout'])) {
            $pageInfo['timeout'] = $this->config['timeout'];
        }

        if (false === $this->checkUnique($pageInfo)) {
            $this->invok('on_add_url_fail', [
                $pageInfo
            ]);
            return;
        }
        $pageInfo['retry'] = isset($pageInfo['retry']) ? $pageInfo['retry'] + 1 : 0;
        $this->queue->push($pageInfo);
    }

    /**
     * checkUnique
     *
     * @param $pageInfo
     *
     * @return
     */
    protected function checkUnique($pageInfo)
    {
        if (empty($pageInfo['url'])) {
            return false;
        }

        if (in_array($pageInfo['type'], ['start'])) {
            return true;
        }
        $pageInfo['context'] = [];
        $pageInfo['url'] = Url::formatUrl($pageInfo['url']);
        $urlMd5 = md5(json_encode($pageInfo));
        if (!$this->uniqueArray->add($urlMd5)) {
            return false;
        }

        return true;
    }

    /**
     * fetchData
     *
     * @param $pageInfo
     * @param $html
     *
     * @return
     */
    protected function fetchData($pageInfo, $html)
    {
        $name = $pageInfo['name'];
        if (!isset($this->config['rule'][$name])) {
            return [];
        }

        $dataRule = $this->config['rule'][$name];
        $data = [];
        if (isset($dataRule['data'])) {
            foreach ($dataRule['data'] as $field => $func) {
                $data[$field] = $func($pageInfo, $html, $data);
            }
        }
        return $data;
    }

    /**
     * fetchLinks
     *
     * @param $pageInfo
     * @param $html
     * @param $data
     *
     * @return
     */
    protected function fetchLinks($pageInfo, $html, $data = [])
    {
        if (empty($html) || empty($pageInfo['url'])) {
            return;
        }
        if ($pageInfo['type'] == 'list' && !empty($data)) {
            foreach ($data as $itemList) {
                foreach ((array)$itemList as $index => $value) {
                    $sliceData = [];
                    foreach (array_keys($data) as $newKey) {
                        if (isset($data[$newKey][$index])) {
                            $sliceData[$newKey] = $data[$newKey][$index];
                        }
                    }
                    $this->fetchSimpleLinks($pageInfo, $html, $sliceData);
                }
            }
        } else {
            $this->fetchSimpleLinks($pageInfo, $html, $data);
        }
    }

    // 检索页面中的连接
    /**
     * fetchSimpleLinks
     *
     * @param $pageInfo
     * @param $html
     * @param $data
     *
     * @return
     */
    protected function fetchSimpleLinks($pageInfo, $html, $data = [])
    {
        foreach ($this->config['rule'] as $rule) {
            if (empty($rule['url'])) {
                continue;
            }
            if (is_callable($rule['url'])) {
               $this->fetchClourseLink($rule, $pageInfo, $html, $data);
            } elseif (strpos($rule['url'], '#') === 0 || strpos($rule['url'], '/') === 0) {
                $this->fetchRegularLink($rule, $pageInfo, $html, $data);
            } else {
                $this->fetchUrlLink($rule, $pageInfo, $html, $data);
            }
        }
    }

    /**
     * fetchClourseLink 
     *
     * @param $rule
     * @param $pageInfo
     * @param $html
     * @param $data
     *
     * @return 
     */
    protected function fetchClourseLink($rule, $pageInfo, $html, $data = [])
    {
        $urlList = (array)call_user_func_array($rule['url'], [
            $pageInfo,
            $html,
            $data
        ]);
        $self = $this;
        array_walk($urlList, function ($url) use ($rule, $pageInfo, $data, $self) {
            $url = Url::rel2abs($url, $pageInfo['url']);
            $url = htmlspecialchars_decode($url);
            $self->addUrl([
                'url' => $url,
                'type' => $rule['type'],
                'name' => $rule['name'],
                'context' => $data,
            ]);
        });

    }

    /**
     * fetchRegularLink
     *
     * @param $rule
     * @param $pageInfo
     * @param $html
     * @param $data
     *
     * @return
     */
    protected function fetchRegularLink($rule, $pageInfo, $html, $data = [])
    {
        $links = $this->setGetLinks($html);
        $regx = trim($rule['url'], '#');
        $regx = trim($regx, '/');

        foreach ($links as $link) {
            $link = Url::rel2abs($link, $pageInfo['url']);
            if (preg_match('#' . $regx . '#is', $link)) {
                // 符合条件
                $link = htmlspecialchars_decode($link);
                $subPageInfo = [
                    'type' => $rule['type'],
                    'name' => $rule['name'],
                    'url' => $link,
                    'context' => $data,
                ];
                $this->addUrl($subPageInfo);
            }
        }
    }

    /**
     * fetchUrlLink
     *
     * @param $rule
     * @param $pageInfo
     * @param $html
     * @param $data
     *
     * @return
     */
    protected function fetchUrlLink($rule, $pageInfo, $html = '', $data = [])
    {
        if (empty($rule['url'])) {
            return;
        }

        $fromPageInfo = explode('.', $rule['url']);
        if (empty($fromPageInfo[0]) || empty($fromPageInfo[1])) {
            return;
        }
        if ($fromPageInfo[0] != $pageInfo['name']) {
            return;
        }
        $urlList = empty($data[$fromPageInfo[1]]) ? '' : $data[$fromPageInfo[1]];
        if (empty($urlList)) {
            return;
        }
        if (!is_array($urlList)) {
            $urlList = [$urlList];
        }
        $this->addUrlList($urlList, $rule, $html, $data);
        $self = $this;
        array_walk($urlList, function ($url) use ($rule, $pageInfo, $data, $self) {
            $url = Url::rel2abs($url, $pageInfo['url']);
            $url = htmlspecialchars_decode($url);
            $self->addUrl([
                'url' => $url,
                'type' => $rule['type'],
                'name' => $rule['name'],
                'context' => $data,
            ]);
        });
    }
}
