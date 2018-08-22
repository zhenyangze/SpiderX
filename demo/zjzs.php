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

if (!is_file('./vendor/autoload.php')) {
    exec("composer require yangze/spiderx");
}

include_once __DIR__ . '/vendor/autoload.php';

$config = [
    'name' => 'zt.zjzs.net',
    'task_num' => 2,
    'start' => [
        'http://zt.zjzs.net/xk2020/',
    ],
    'rule' => [
        [
            'name' => 'list',
            'type' => 'list',
            'url' => '#xk2020/area_\d_\d.html#',
            'data' => [
            ]
        ],
        [
            'name' => 'detail',
            'type' => 'detail',
            'data' => [
            ],
        ]
    ]
];

$spider = new SpiderX\SpiderX($config);
$spider->on_fetch_list = function ($pageInfo, $html, $data) use ($spider) {
    $tableHtml = Util::subStrByStr('<td width="60"><b>地区</b></td>', '</table>', $html, true);
    if (empty($tableHtml)) {
        return;
    }
    $tableHtml = '<table ><tr><td width="60"><b>地区</b></td>' . $tableHtml  . '</table>';
    $tableArray = Util::tableToArray($tableHtml);

    array_walk($tableArray, function(&$line) {
        array_walk($line, function(&$item){
            $item = str_ireplace(',', '，', $item);
            $item = trim($item);
        });
    });

    foreach($tableArray as $key => $line) {
        if (empty($key) || empty($line[1])) {
            continue;
        }

        $spider->addUrl([
            'name' => 'detail',
            'type' => 'detail',
            'url' => 'http://zt.zjzs.net/xk2020/' . $line[1] . '.html',
            'context' => [
                'province' => $line[0],
                'id' => $line[1],
                'school_name' => $line[2],
                'web_url' => $line[3]
            ]
        ]);
    }
};
$spider->on_loadding_list = function ($pageInfo) {
    return true;
};
$spider->on_fetch_detail = function ($pageInfo, $html, $data) {
    $tableHtml = Util::subStrByStr('<th><b>层次</b></td>', '</table>', $html, true);
    if (empty($tableHtml)) {
        return;
    }
    $tableHtml = str_ireplace(['<tr>', '<br/>'], ['</tr>', '，'], $tableHtml);
    $tableHtml = '<table><tr><th><b>层次</b></td>' . $tableHtml . '</table>';
    $tableArray = Util::tableToArray($tableHtml);
    array_walk($tableArray, function(&$line) {
        array_walk($line, function(&$item){
            $item = str_ireplace(',', '，', $item);
            $item = trim($item);
        });
    });

    foreach($tableArray as $key => $line) {
        if (empty($key)) {
            continue;
        }
        file_put_contents(__DIR__ . '/data.csv', implode(',', [
            $pageInfo['context']['province'],
            $pageInfo['context']['id'],
            $pageInfo['context']['school_name'],
            $pageInfo['context']['web_url'],
        ]) . ',' . implode(',', $line) . "\n", FILE_APPEND | LOCK_EX);
    }
};
$spider->start();