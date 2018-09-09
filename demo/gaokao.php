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
    'name' => '2018年具有高校自主招生入选资格的考生名单',
    'tasknum' => 3,
    'start' => [
        'https://gaokao.chsi.com.cn/zsgs/zzxblqzgmd--method-groupByYx,zslx-1.dhtml'
    ],
    'rule' => [
        [
            'name' => 'school_list',
            'type' => 'list',
            'url' => '#zzxblqzgmd--method-groupBy#',
            'data' => [
            ]
        ],
        [
            'name' => 'detail',
            'type' => 'detail',
            'url' => '#zzxblqzgmd--method-listStu#',
            'data' => [
            ]
        ]
    ],
];

$spider = new SpiderX\SpiderX($config);
$spider->on_fetch_detail = function ($pageInfo, $html, $data) {
    $html = str_ireplace('<br />', '', $html);
    $html = str_ireplace('<br/>', ';', $html);
    $html = str_ireplace('<br>', ';', $html);
    $schoolName = Util::subStrByStr('s="center">2018年', '具有高校自主招生', $html, true);
    $dataList = (new \SpiderX\Lib\UtilXpath)->setAttr(['title'])->setHtml($html)->setRange('//table[@id="YKTabCon2_10"]')->getResult();
    foreach($dataList as $data) {
        array_walk($data, function (&$item) {
            $item = str_ireplace(',', '，', $item);
            $item = trim($item);
        });
        file_put_contents('data.csv', $schoolName . ',' . implode(',', $data) . "\n", FILE_APPEND | LOCK_EX);
    }
};
$spider->on_add_url = function ($pageInfo) {
    return true;
};
$spider->start();