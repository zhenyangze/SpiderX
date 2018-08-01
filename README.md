# php爬虫脚本

------

php爬虫框架。

> * 框架只做分发，不做数据处理，需要自己定制。
> * 不限制采集方式，可以用正则，Xpath，字符串截取。
> * 无限层级采集，可以实现列表->详情，列表->列表->详情，详情->详情等任意姿势采集。
> * 队列去重，可以重新抓取，也可以分次采集。
> * 支持调试模式，实时报表，守护模式。

## 执行方式
```shell
Usage:
  demo/sina.php {command} [--opt -v -h ...] [arg0 arg1 arg2=value2 ...]

Options:
      --debug     Setting the application runtime debug level
      --profile   Display timing and memory usage information
      --no-color  Disable color/ANSI for message output
  -h, --help      Display this help message
  -V, --version   Show application version information

Internal Commands:
  help      Show application help information
  list      List all group and alone commands
  version   Show application version information

Available Commands:

- Alone Commands
  daemon    Run script in daemon modal
  debug     Run script in debug modal
  run       Run script with report
  status    Show the SpiderX status
  stop      Stop all the spiderX Process

More command information, please use: demo/sina.php {command} -h
```

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
- [x] 命令行效果。
- [x] 异步多线程。
