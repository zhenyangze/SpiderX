# php爬虫脚本
- 框架只做分发，不做数据处理，需要自己在回调中定制。
- 不限制采集方式，可以用正则，Xpath，字符串截取。
- 无限层级采集，可以实现列表->详情，列表->列表->详情，详情->详情等任意姿势采集。
- 队列去重，可以增量抓取，也可以全量采集。
- 支持调试模式，实时报表，守护模式。

## 安装依赖

| 环境 | 说明 |
|-------:|--------|
| php | >5.6,最好是php7以上 |
| redis |数据队列  |

## 快速开始
### 1、复制代码到index.php文件中
```php
if (!is_file('./vendor/autoload.php')) {
    exec("composer require yangze/spiderx");
}

include_once __DIR__ . '/vendor/autoload.php';

$config = [
    'name' => 'sina',
    'tasknum' => 1,
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
                }
            ]
        ],
    ]
];

$spider = new SpiderX\SpiderX($config);
$spider->on_fetch_list = function ($pageInfo, $html, $data) {
    file_put_contents(__DIR__ . '/data.txt', implode("\n", $data['title']) . "\n", FILE_APPEND | LOCK_EX);
};
$spider->start();
```
### 2、命令行执行(需要composer下载依赖，时间跟网速有关)
```php
php index.php run
```

## 配置说明

| 字段 | 类型 | 说明 |
|--------:|--------|--------|
|name   | string | 任务名称，队列名称根据name值生成。如果要做分布式的，可以选择用相同的name值 |
|tasknum| int | 任务数量，默认为1|
|start  | array |采集入口url|
|rule   | array |采集规则，具体参考下方说明|

### rule值说明
rule值为数组形式，每个二级元素为一个单元。

| 字段 | 类型 | 说明 |
|--------|--------|--------|
|name| string | 任务名称，队列名称根据name值生成。如果要做分布式的，可以选择用相同的name值 |
|name| string | 页面类型，选项为list 或者detail |
|url| string |入口url,有2种形式，一种是'#article_\d+#'这样的正则，从各个页面中抓取；一种是取其他单元的name值与所取单元的data值组合，比如一个单元name为news_list,data中有个元素为url,则组合成news_list.url赋值给当前字段|
|data| array |要采集的数据，以回调方式赋值，形式为：`key => function ($pageInfo, $html, $data) { return '';}`|

## 回调说明
### 通用回调方法
开始任务
```php
on_start = function() use($spiderx) {
	// 可以在此方法中添加用户登录，增加url队列操作
    //$spiderx->addUrl([]);
}
```

任务完成
```php
on_finish = function() {
	// 任务执行完成，可以发送通知，导入数据库，删除日志文件等
}
```

向队列中添加url数据
```php
on_add_url = function($pageInfo) {
	// 如果调转当前回调，需要返回true,才会向队列中添加数据
}
```

重试，url请求失败，重新请求，默认为3次
```php
on_retry_page = function($pageInfo) {
	//返回true表示需要重试
}
```

如果获取不到html数据，可以重写setGetHtml方法
```php
setGetHtml = function($pageInfo) {
	return file_get_contents($pageInfo['url']);
}
```

类似的还有setGetLinks方法，抽取页面中的链接，或者其他url存储方式
```php
setGetLinks = function($html) {
	
}
```

### 页面加载回调
需要依赖用户设置的每个rule下面单元的name值。假设我们设置的name值为news. 则对应的回调方法有：

请求url前回调
```php
on_loadding_{news,需要替换不同的name值} = function($pageInfo) {
	// pageInfo 为当前页面的相关信息
	//返回true表示需要请求这个页面
}
```

获取html后回调
```php
on_loaded_{news,需要替换不同的name值} = function($pageInfo, $html) {
	//html表示当前的html数据
}
```
解析页面数据后回调，一般用于保存数据
```php
on_fetch_{news,需要替换不同的name值} = function($pageInfo, $html, $data) {
	//data值为解析的数据
}
```

## 高级玩法
### 无限级数据采集
实现的方式就是在data的单元中，把url的值设置为上一个单元的`name.DataField`的形式
> 参考demo目录sina文件。

### post提交表单，post分页抓取数据
实现方式为自定义添加url队列，请求类型method为post，请求参数query为数组或者字符串形式：
```php
$spider->addUrl([
    'type' => 'detail', // 保持和单元的name，type一致
    'name' => 'detail',
    'url' => 'http://smeimdf.mofcom.gov.cn/news/searchEntpAudit.jsp',
    'method' => 'post', // 请求方式
    'query' => [ // 请求参数
        'fund_type' => $fund_type,
        'province' => 340000,
    ],
    'context' => [ // 上下文数据，可以很方便的在多任务中传数据
        'fund_type' => $fund_type,
        'province' => '-',
        'province_name' => '-',
    ]
]);

```

### 快速导出列表数据和表格数据
```php
    $dataList = (new \SpiderX\Lib\UtilXpath)->setAttr(['title'])->setHtml($html)->setRange('//table[@id="YKTabCon2_10"]')->getResult();
    foreach($dataList as $data) {
        array_walk($data, function (&$item) {
            $item = str_ireplace(',', '，', $item);
            $item = trim($item);
        });
        file_put_contents('data.csv', implode(',', $data) . "\n", FILE_APPEND | LOCK_EX);
    }
```



## 执行效果
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

## 代码参考
参考demo目录

## 效果参考
### 命令行
![command](https://github.com/zhenyangze/Spiderx/blob/master/demo/img/command.jpg?raw=true)

### 报表模式
![report](https://raw.githubusercontent.com/zhenyangze/Spiderx/master/demo/img/spiderx.jpg)

### 守护模式：
运行后看不到，不截图了。


## 后续功能
- [ ] 脚手架，自动生成代码
- [ ] 支持深度优先和广度优先
- [x] 命令行效果
- [x] 异步多线程
