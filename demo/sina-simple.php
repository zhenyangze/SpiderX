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
