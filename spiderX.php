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
/**
include_once __DIR__ . '/spider.php';

$config = [
    'start-url' => [
        'https://www.jb51.net/list/list_123_1.htm'
    ],
    'list-url-rule' => [
        'list/list_123_\d+.htm',
    ],
    'content-url-rule' => [
        'article/\d+.htm'
    ]
];

$spider = new Spider($config);
$spider->on_deal_content = function ($html, $urlInfo) {
    echo '[' . $urlInfo['type'] . ']' . $urlInfo['url'] . "\n";
};

$spider->on_deal_list = function ($html, $urlInfo) {
    echo '[' . $urlInfo['type'] . ']' . $urlInfo['url'] . "\n";
};
$spider->start();
 */
class SpiderX
{
    protected $config;
    protected $queue;
    protected $checkQueue; // 用于检测重复
    protected $deep = false; // 是否在文章页面也抓取数据

    public function __construct($config = [])
    {
        $this->config = $config;
        $this->config['rule'] = array_column($this->config['rule'], null, 'name');
        $this->init();
    }

    public function __call($method, $args)
    {
        if (isset($this->$method)) {
            return call_user_func_array($this->$method, $args);
        }
    }

    public function start()
    {
        // 执行前可以添加数据
        $this->invok('on_start');

        // 添加首页
        if (!empty($this->config['start-url'])) {
            array_walk($this->config['start-url'], function ($url) {
                $this->addUrl($url, 'start');
            });
        }

        while ($this->queue->length()) {
            $urlInfo = $this->queue->pop();

            // 加载前校验
            if (!$this->invok('on_prepare_' . $urlInfo['name'], [
                $urlInfo
            ], true)) {
                continue;
            }

            // get or post
            $html = $this->setGetHtml($urlInfo);

            //列表和详情共用一套
            $data = $this->fetchData($urlInfo, $html);

            // 回调
            $userData = $this->invok('on_fetch_' . $urlInfo['name'], [
                $urlInfo,
                $html,
                $data,
            ]);
            $data = empty($userData) ? $data : $userData;

            // 处理子链
            $this->fetchLinks($urlInfo, $html, $data);
        }
        
        $this->invok('on_finish');
    }

    protected function invok($func, $args = [], $default = [])
    {
        if (!isset($this->$func)) {
            return $default;
        }
        return call_user_func_array($this->$func, $args);
    }

    protected function init()
    {
        $this->queue = new Queue();
        $this->checkQueue = new UniqArray();
        if (!isset($this->setGetHtml)) {
            $this->setGetHtml = function ($url) {
                usleep(200);
                return file_get_contents($url);
            };
        }
        if (!isset($this->setGetLinks)) {
            $this->setGetLinks = function ($str) {
                preg_match_all('/href=([\'"]?)([^\s><\'"]*)\1/is', $str, $match);
                $links = isset($match['2']) ? $match[2] : [];
                unset($match);
                unset($str);
                /**
                $dom = new DOMDocument();
                @$dom->loadHTML($str);
                $xpath = new DOMXPath($dom);
                $hrefs = $xpath->evaluate("/html/body//a");
                for ($i = 0; $i < $hrefs->length; $i++) {
                    $href = $hrefs->item($i);
                    $links[] = $href->getAttribute('href');
                }*/
                return $links;
            };
        }
    }
    public function addUrl($url, $type, $name, $params = [])
    {
        $url = $this->formatUrl($url);
        $urlMd5 = md5($url);
        if (!$this->checkQueue->add($urlMd5)) {
            return;
        }
        $this->queue->push([
            'url' => $url,
            'type' => $type,
            'name' => $name,
            'params' => $params,
            'retry' => 0,
        ]);
    }

    public function formatUrl($url)
    {
        $urlInfo = parse_url($url);
        foreach ($urlInfo as $field => $value) {
            switch ($field) {
                case 'scheme':
                    $urlInfo[$field] .= '://';
                    break;
                case 'query':
                    $urlInfo[$field] = '?' . $urlInfo[$field];
                    break;
                case 'fragment':
                    $urlInfo[$field] = '';
                    break;
            }
        }

        return implode('', $urlInfo);
    }

    protected function parseLink($links = [], $baseUrl = '', $params = [])
    {
        foreach ((array)$links as $url) {
            if (empty($url)) {
                continue;
            }
            $realUrl = $this->rel2abs($url, $baseUrl);

            foreach ($this->config['content-url-rule'] as $rule) {
                if (preg_match('#' . $rule . '#is', $realUrl)) {
                    $this->addUrl($realUrl, 'content', $params);
                    break;
                }
            }

            foreach ($this->config['list-url-rule'] as $rule) {
                if (preg_match('#' . $rule . '#is', $realUrl)) {
                    $this->addUrl($realUrl, 'list');
                    break;
                }
            }
        }
    }
    public function rel2abs($rel, $base)
    {
        /* return if already absolute URL */
        if (parse_url($rel, PHP_URL_SCHEME) != '') {
            return $rel;
        }

        /* queries and anchors */
        if ($rel[0]=='#' || $rel[0]=='?') {
            return $base.$rel;
        }

        /* parse base URL and convert to local variables:
        $scheme, $host, $path */
        extract(parse_url($base));

        /* remove non-directory element from path */
        $path = preg_replace('#/[^/]*$#', '', $path);

        /* destroy path if relative url points to root */
        if ($rel[0] == '/') {
            $path = '';
        }

        /* dirty absolute URL */
        $abs = "$host$path/$rel";

        /* replace '//' or '/./' or '/foo/../' with '/' */
        $re = array('#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#');
        for ($n=1; $n>0; $abs=preg_replace($re, '/', $abs, -1, $n)) {
        }

        /* absolute URL is ready! */
        return $scheme.'://'.$abs;
    }

    protected function fetchData($urlInfo, $html) {
        $name = $urlInfo['name'];

        $dataRule = $this->config['rule'][$name];
        $data = [];
        foreach($dataRule as $field => $func) {
            $data[$field] = $func($urlInfo, $html, $data);
        }
        
        return $data;
    }

    protected function fetchLinks($urlInfo, $html, $data) {
        if ($urlInfo['type'] == 'list') {
            foreach($data as $field => $itemList) {
                foreach($itemList as $index => $value) {
                    $sliceData = [];
                    foreach(array_keys($data) as $newKey) {
                        $sliceData[$newKey] = $data[$newKey][$index];
                    }
                    $this->fetchSimpleLinks($urlInfo, $html, $data);
                }
            }
        }  else {
            $this->fetchSimpleLinks($urlInfo, $html, $data);
        }
    }

    // 检索页面中的连接
    protected function fetchSimpleLinks($urlInfo, $html, $data) {
        $this->fetchRegularLink($urlInfo, $html, $data);
        $this->fetchUrlLink($urlInfo, $html, $data);
    }

    protected function fetchRegularLink($urlInfo, $html, $data) {

    }

    protected function fetchUrlLink($urlInfo, $html, $data) {
        foreach($this->config['rule'] as $rule) {
            if (empty($rule['url'])) {
                continue;
            }

            $fromUrlInfo = explode('.', $rule['url']);
            if (empty($fromUrlInfo[0]) || empty($fromUrlInfo[1])) {
                continue;
            }
            if ($fromUrlInfo[0] != $urlInfo['name']) {
                continue;
            }
            $url = empty($data[$fromUrlInfo[1]]) ? '' : $data[$fromUrlInfo[1]];
            if (empty($url)) {
                continue;
            }
            $subUrlInfo = [
                'type' => $rule['type'],
                'name' => $rule['name'],
                'url' => $url,
                'params' => $data,
            ];

            $this->addUrl($url, $rule['type'], $rule['name'], $data);
        }
    }
}

class Queue
{
    public function __construct($key = '')
    {
        if (empty($key)) {
            $key = 'YANGZE:' . md5(uniqid());
        }
        $this->key = $key;
        $this->redis = new Redis();
        $this->redis->connect('127.0.0.1', 6379);
    }

    public function __destruct()
    {
        $this->redis->delete($this->key);
    }

    public function length()
    {
        return $this->redis->llen($this->key);
    }

    public function push($data)
    {
        $data = serialize($data);
        return $this->redis->lpush($this->key, $data);
    }

    public function pop()
    {
        $data = $this->redis->rpop($this->key);
        return unserialize($data);
    }
}

class UniqArray
{
    public function __construct($key = '')
    {
        if (empty($key)) {
            $key = 'YANGZE:' . md5(uniqid());
        }
        $this->key = $key;
        $this->redis = new Redis();
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
