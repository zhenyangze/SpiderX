<?php
/**
 * Short description for jb51.php
 *
 * @package jb51
 * @author zhenyangze <zhenyangze@gmail.com>
 * @version 0.1
 * @copyright (C) 2018 zhenyangze <zhenyangze@gmail.com>
 * @license MIT
 */

include_once __DIR__ . '/../vendor/autoload.php';

$config = [
    'name' => 'jb51',
    'tasknum' => 2,
    //'modal' => 'debug', // debug,daemonize,默认为实时报表模式
    'start' => [
        'https://www.jb51.net/list/list_6_1.htm',
    ],
    'rule' => [
        [
            'name' => 'list',
            'type' => 'list',
            'url' => '#list_6_\d+.htm#',
        ],
        [
            'name' => 'content',
            'type' => 'detail',
            'url' => '#article/\d+.htm#',
            'data' => [
                'title' => function ($pageInfo, $html, $data) {
                    preg_match_all('/<title>(.*?)<\/title>/i', $html, $matches);
                    return $matches[1][0];
                },
            ]
        ]
    ]
];
$spider = new SpiderX\SpiderX($config);
$spider->on_fetch_content = function ($pageInfo, $html, $data) use ($spider) {
    file_put_contents(__DIR__ . '/data/' . implode('_', [
        $spider->config['name'],
        $pageInfo['name']
    ]) . '.csv', date('[Y-m-d H:i:s]') . $data['title'] . "\n", FILE_APPEND | LOCK_EX);
};
$spider->start();