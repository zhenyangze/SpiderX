<?php
/**
 * Short description for url.php
 *
 * @package url
 * @author zhenyangze <zhenyangze@gmail.com>
 * @version 0.1
 * @copyright (C) 2018 zhenyangze <zhenyangze@gmail.com>
 * @license MIT
 */
namespace SpiderX\Lib;
use GuzzleHttp\Client;

class Url {
    public static $instance = [];
    public static function formatUrl($url) {
        $pageInfo = parse_url($url);
        foreach ($pageInfo as $field => $value) {
            switch ($field) {
            case 'scheme':
                $pageInfo[$field] .= '://';
                break;
            case 'query':
                $pageInfo[$field] = '?' . $value;
                break;
            case 'fragment':
                $pageInfo[$field] = '';
                break;
            }
        }
        return implode('', $pageInfo);
    }

    public static function rel2abs($rel, $base)
    {
        if (empty($rel)) {
            return;
        }
        if (parse_url($rel, PHP_URL_SCHEME) != '') {
            return $rel;
        }

        if ($rel[0]=='#' || $rel[0]=='?') {
            return $base.$rel;
        }

        extract(parse_url($base));

        $path = preg_replace('#/[^/]*$#', '', $path);

        if ($rel[0] == '/') {
            $path = '';
        }

        $abs = "$host$path/$rel";

        $re = array('#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#');
        for ($n=1; $n>0; $abs=preg_replace($re, '/', $abs, -1, $n)) {
        }
        return $scheme.'://'.$abs;
    }

    public static function getLinks($html) {
        preg_match_all('/href=([\'"]?)([^\s><\'"]*)\1/is', $html, $match);
        $links = isset($match['2']) ? $match[2] : [];
        return $links;
    }

    public static function getHtml($pageInfo = '') {
        if (empty($pageInfo['url'])) {
            return '';
        }

        $timeout = isset($pageInfo['timeout']) ? $pageInfo['timeout'] : 3;
        if (!isset(self::$instance['client'])) {
            self::$instance['client'] = new Client([
                'cookies' => isset($pageInfo['cookie']),
                'timeout' => $timeout,
            ]);
        }

        $method = isset($pageInfo['method']) ? strtoupper($pageInfo['method']) : 'GET';
        $sendData = [];
        if ($method == 'POST') {
            $query = isset($pageInfo['query']) ? $query : '';
            if(is_array($pageInfo['query'])) {
                $sendData['form_params'] = $query;
            } else {
                $sendData['body'] = $query;
            }
        }

        $html = '';
        try {
            $response = self::$instance['client']->request($method, $pageInfo['url'], $sendData);
            $html = $response->getBody();
        } catch (Exception $e) {
            $html = '';
        }
        $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8,GBK,GB2312,BIG5');
        return $html;
    }
}
