<?php

use SpiderX\Lib\Url;
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

include_once __DIR__ . '/../vendor/autoload.php';

$config = [
    'name' => 'test clourse',
    'tasknum' => 1,
    'start' => [
        'https://www.jb51.net/list/list_114_1.htm'
    ],
    'rule' => [
        [
            'name' => 'list',
            'type' => 'list',
            'url' => function ($pageInfo, $html, $data) {
                return ['https://www.jb51.net/list/list_114_2.htm'];
            },
            'data' => [
            ]
        ],
        [
            'name' => 'detail',
            'type' => 'detail',
            'url' => 'list.url',
            'data' => [
                'title' => function($pageInfo, $html, $data) {
                    return Util::subStrByStr('<title>', '</title>', $html, true);
                },
                'link' => function($pageInfo, $html, $data) {
                    return $pageInfo['url'];
                }
            ]
        ]
    ],
];

$spider = new \SpiderX\SpiderX($config);
$spider->on_fetch_list = function ($pageInfo, $html, $data) {
    $dataList = (new \SpiderX\Lib\UtilXpath)->setAttr(['title'])->setHtml($html)->setRepeat('//div[contains(@class, "artlist")]/dl/dt')->getResult(true);
    $urlList = $dataList[2];
    array_walk($urlList, function(&$url) use ($pageInfo) {
        $url = Url::rel2abs($url, $pageInfo['url']);
    });
    $data = [
        'date' => $dataList[0],
        'title' => $dataList[1],
        'url' => $urlList,
        'attr' => $dataList[3],
    ];
    return $data;
};
$spider->on_fetch_detail = function ($pageInfo, $html, $data) {
    echo '【' . $data['link'] . '】' . $data['title'] . "\n";
};
$spider->on_start = function () {
    return true;
};
$spider->on_add_url = function ($pageInfo) {
    return true;
};
$spider->on_add_url_fail = function ($pageInfo) {
    return true;
};
$spider->on_loadding_detail = function ($pageInfo) {
    return true;
};
$spider->on_loaded_detail = function ($pageInfo, $html) {
    return true;
};
$spider->on_finish = function () {
    return true;
};
$spider->start();