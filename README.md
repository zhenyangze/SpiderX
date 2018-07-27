# php爬虫脚本

------

php爬虫框架。

> * 框架只做分发，不做数据处理，需要自己定制。
> * 不限制采集方式，可以用正则，Xpath，字符串截取。
> * 无限层级采集，可以实现列表->详情，列表->列表->详情，详情->详情等任意姿势采集。
> * 队列去重，可以重新抓取，也可以分次采集。

## 代码参考(参考demo目录)
```php
include_once __DIR__ . '/../vendor/autoload.php';

$config = [
    'name' => 'sina',
    'start' => [
        'http://roll.news.sina.com.cn/news/gnxw/gdxw1/index.shtml',
    ],
    'rule' => [
        [
            'name' => 'list',
            'type' => 'list',
            'url' => '#gdxw1/index_\d+.shtml#',
            'data' => [
                'title' => function ($pageInfo, $html, $data) {
                    preg_match_all('/<li><a href=".*?" target="_blank">(.*?)<\/a><span>/i', $html, $matches);
                    return $matches[1];
                },
                'link' => function ($pageInfo, $html, $data) {
                    preg_match_all('/<li><a href="(.*?)" target="_blank">(.*?)<\/a><span>/i', $html, $matches);
                    return $matches[1];
                },
            ]
        ],
    ]
];

$spider = new SpiderX\SpiderX($config);
$spider->on_fetch_list = function ($pageInfo, $html, $data) {
    echo '[' . $pageInfo['type'] . ']' . $pageInfo['url'] . "\n";
};
$spider->start();
```


## 后续功能

- [ ] 脚手架，自动生成代码。
- [ ] 消息通知。
- [ ] 命令行效果。
- [ ] 异步多线程。