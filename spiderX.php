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
        while ($this->queue->length()) {
            $pageInfo = $this->queue->pop();

            // 加载前校验
            if (!$this->invok('on_prepare_' . $pageInfo['name'], [
                $pageInfo
            ], true)) {
                continue;
            }

            // get or post
            $html = $this->setGetHtml($pageInfo);

            //列表和详情共用一套
            $data = $this->fetchData($pageInfo, $html);

            // 回调
            $userData = $this->invok('on_fetch_' . $pageInfo['name'], [
                $pageInfo,
                $html,
                $data
            ]);
            $data = empty($userData) ? $data : $userData;

            // 处理子链
            $this->fetchLinks($pageInfo, $html, $data);
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
            $this->setGetHtml = function ($pageInfo) {
                if (isset($pageInfo['method']) && strtolower($pageInfo['method']) == 'post') {
                    $data = $pageInfo['query'];
                    $url = $pageInfo['url'];
                    $content = http_build_query($data);
                    $length = strlen($content);
                    $options = array(
                        'http' => array(
                            'method' => 'POST',
                            'header' =>
                            "Content-type: application/x-www-form-urlencoded\r\n" .
                            "Content-length: $length \r\n",
                            'content' => $content
                        )
                    );
                    $html = file_get_contents($url, false, stream_context_create($options));
                } else {
                    $html = file_get_contents($pageInfo['url']);
                }
                usleep(200);
                return mb_convert_encoding($html, 'UTF-8', 'UTF-8,GBK,GB2312,BIG5');
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
    public function addUrl($pageInfo = [])
    {
        if (empty($pageInfo['url'])) {
            return;
        }
        if ($pageInfo['type'] == 'list') {
            $pageInfo['context'] = [];
        }
        $pageInfo['url'] = $this->formatUrl($pageInfo['url']);
        $urlMd5 = md5(json_encode($pageInfo));
        if (!$this->checkQueue->add($urlMd5)) {
            return;
        }
        $pageInfo['retry'] = isset($pageInfo['retry']) ? $pageInfo['retry'] + 1 : 0;
        $this->queue->push($pageInfo);
    }

    public function formatUrl($url)
    {
        $pageInfo = parse_url($url);
        foreach ($pageInfo as $field => $value) {
            switch ($field) {
                case 'scheme':
                    $pageInfo[$field] .= '://';
                    break;
                case 'query':
                    $pageInfo[$field] = '?' . $pageInfo[$field];
                    break;
                case 'fragment':
                    $pageInfo[$field] = '';
                    break;
            }
        }

        return implode('', $pageInfo);
    }

    public function rel2abs($rel, $base)
    {
        if (empty($rel)) {
            return;
        }
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

    protected function fetchData($pageInfo, $html) {
        $name = $pageInfo['name'];
        if (!isset($this->config['rule'][$name])) {
            return [];
        }

        $dataRule = $this->config['rule'][$name];
        $data = [];
        foreach($dataRule['data'] as $field => $func) {
            $data[$field] = $func($pageInfo, $html, $data);
        }
        return $data;
    }

    protected function fetchLinks($pageInfo, $html, $data = []) {
        if ($pageInfo['type'] == 'list') {
            foreach($data as $field => $itemList) {
                foreach($itemList as $index => $value) {
                    $sliceData = [];
                    foreach(array_keys($data) as $newKey) {
                        $sliceData[$newKey] = $data[$newKey][$index];
                    }
                    $this->fetchSimpleLinks($pageInfo, $html, $sliceData);
                }
            }
        }  else {
            $this->fetchSimpleLinks($pageInfo, $html, $data);
        }
    }

    // 检索页面中的连接
    protected function fetchSimpleLinks($pageInfo, $html, $data = []) {
        foreach($this->config['rule'] as $rule) {
            if (empty($rule['url'])) {
                continue;
            }
            if (strpos('#', $rule['url']) !== false | strpos($rule['url'], '/') !== false) {
                $this->fetchRegularLink($rule, $pageInfo, $html, $data);
            } else {
                $this->fetchUrlLink($rule, $pageInfo, $html, $data);
            }
        }

    }

    protected function fetchRegularLink($rule, $pageInfo, $html, $data = []) {
        $links = $this->setGetLinks($html);
        $regx = trim($rule['url']);

        foreach($links as $link) {
            $link = $this->rel2abs($link, $pageInfo['url']);
            if (preg_match('#' . $regx . '#is', $link)) {
                // 符合条件
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

    protected function fetchUrlLink($rule, $pageInfo, $html = '', $data = []) {
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
        array_walk($urlList, function($url) use($rule, $data) {
            $this->addUrl([
                'url' => $url,
                'type' => $rule['type'],
                'name' => $rule['name'],
                'context' => $data,
            ]);
        });
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
