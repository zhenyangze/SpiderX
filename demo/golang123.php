<?php

use SpiderX\Lib\Util;

/**
 * Short description for index.php
 *
 * @package index
 * @author zhenyangze <zhenyangze@gmail.com>
 * @version 0.1
 * @copyright (C) 2018 zhenyangze <zhenyangze@gmail.com>
 * @license MIT
 */

if (!is_file("./vendor/autoload.php")) { 
    exec("composer require yangze/spiderx"); 
} 

include_once __DIR__ . "/vendor/autoload.php"; 

$config = [ 
    "name" => "golang123", 
    "tasknum" => 3, 
    "start" => [ 
        "https://www.golang123.com/?cate=&pageNo=1", 
    ], 
    "rule" => [ 
        [ 
            "name" => "list", 
            "type" => "list", 
            "url" => "#cate=&pageNo=#", 
            "data" => [ 
            ], 
        ], 
        [ 
            "name" => "detail", 
            "type" => "detail", 
            "url" => "#/topic/#", 
            "data" => [ 
                "title" => function($pageInfo, $html, $data) { 
                    return Util::subStrByStr('<h1>', '</h1>', $html, true); 
                }, 
                "time" => function($pageInfo, $html, $data) { 
                    return Util::subStrByStr('发布于', '</span>', $html, true); 
                }, 
                "author" => function($pageInfo, $html, $data) { 
                    return Util::subStrByStr('作者', '</span>', $html, true);
                }, 
                "body" => function($pageInfo, $html, $data) { 
                    return Util::subStrByXpath($html, "//div[@class='home-articles-box']", 'html', '', true);
                }, 
            ], 
        ], 
    ], 
]; 

$spider = new SpiderX\SpiderX($config); 
$spider->on_start = function () { 
    // 模拟登录 
    return true; 
}; 
$spider->on_add_url = function ($pageInfo) { 
    return true; 
}; 
$spider->on_add_url_fail = function ($pageInfo) { 
    return true; 
}; 

// ------ list start ------ 
$spider->on_loadding_list = function ($pageInfo) { 
    return true; 
}; 
$spider->on_loaded_list = function ($pageInfo, $html) { 
    return true; 
}; 
$spider->on_fetch_list = function ($pageInfo, $html, $data) { 
    // 获取数据，可执行保存逻辑 
    //$data = (new SpiderXLibUtilXpath)->setAttr(["title"])->setHtml($html)->setRange("//table[@id="YKTabCon2_10"]")->getResult(); 
    return $data; 
}; 
// ------ list end ------ 

// ------ detail start ------ 
$spider->on_loadding_detail = function ($pageInfo) { 
    return true; 
}; 
$spider->on_loaded_detail = function ($pageInfo, $html) { 
    return true; 
}; 
$spider->on_fetch_detail = function ($pageInfo, $html, $data) { 
    // 获取数据，可执行保存逻辑 
    //$data["title"] = SpiderXLibUtil::subStrByStr("", $html, true); 
    return $data; 
}; 
// ------ detail end ------ 

$spider->on_finish = function () { 
    return true; 
}; 
$spider->start();
