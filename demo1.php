<?php
/**
 * Short description for demo.php
 *
 * @package demo
 * @author zhenyangze <zhenyangze@gmail.com>
 * @version 0.1
 * @copyright (C) 2018 zhenyangze <zhenyangze@gmail.com>
 * @license MIT
 */

include_once __DIR__ . '/spiderX.php';

$config = [
    'start' => [
        'http://roll.news.sina.com.cn/news/gnxw/gdxw1/index.shtml',
    ],
    'rule' => [
        'name' => 'list',
        'type' => 'list',
        'rule' => [
            'gdxw1/index_\d+.shtml'
        ],
        'data' => [
            'title' => '',
            'link' => '',
        ]
    ],
    [
        'name' => 'detail',
        'type' => 'detail',
        'url' => 'list.link',
        /**
        'rule' => [
            '/\d+-\d+-\d+/'
        ],*/
        'data' => [
            'id' => '',
            'title' => '',
            'link' => '',
            'blog' => [], // list 数据,如果有继承
        ]
    ],
    [
        'name' => 'blog-detail',
        'type' => 'content',
        'url' => 'detail.blog',
        'data' => [
            'title' => '',
            'link' => '',
        ]
    ]
];

$spider = new Spider($config);
$spider->on_fetch_detail = function ($html, $urlInfo) {
    echo '[' . $urlInfo['type'] . ']' . $urlInfo['url'] . "\n";
};

$spider->on_fetch_list = function ($html, $urlInfo) {
    echo '[' . $urlInfo['type'] . ']' . $urlInfo['url'] . "\n";
};
$spider->start();
