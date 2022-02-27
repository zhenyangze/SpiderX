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
use GuzzleHttp\Exception\TransferException;

/**
 * 
 */
class Url
{
    /**
     * 
     */
    public static $instance = [];
    /**
     * formatUrl 
     *
     * @param $url
     *
     * @return 
     */
    public static function formatUrl($url)
    {
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

    /**
     * rel2abs 
     *
     * @param $rel
     * @param $base
     *
     * @return 
     */
    public static function rel2abs($rel, $base)
    {
        if (empty($rel)) {
            return;
        }
        if (parse_url($rel, PHP_URL_SCHEME) != '') {
            return $rel;
        }

        if ($rel[0] == '#' || $rel[0] == '?') {
            return $base . $rel;
        }

        extract(parse_url($base));
        if (empty($path)) {
            $path = '/';
        }

        $path = preg_replace('#/[^/]*$#', '', $path);
        if (stripos($rel, '//') === 0) {
            return $scheme . ':' . $rel;
        } elseif ($rel[0] == '/') {
            $path = '';
        }

        $abs = "$host$path/$rel";

        $re = array('#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#');
        for ($n = 1; $n > 0; $abs = preg_replace($re, '/', $abs, -1, $n)) {
        }
        return $scheme . '://' . $abs;
    }

    /**
     * getLinks 
     *
     * @param $html
     *
     * @return 
     */
    public static function getLinks($html)
    {
        preg_match_all('/href=([\'"]?)([^\s><\'"]*)\1/is', $html, $match);
        $links = isset($match['2']) ? $match[2] : [];
        return $links;
    }

    /**
     * getHtml 
     *
     * @param $pageInfo
     *
     * @return 
     */
    public static function getHtml($pageInfo = '')
    {
        if (empty($pageInfo['url'])) {
            return '';
        }

        $timeout = isset($pageInfo['timeout']) ? $pageInfo['timeout'] : 3;
        if (!isset(self::$instance['client'])) {
            self::$instance['client'] = new Client([
                'cookies' => isset($pageInfo['cookie']),
                'timeout' => $timeout,
                'http_errors' => false
            ]);
        }

        $method = isset($pageInfo['method']) ? strtoupper($pageInfo['method']) : 'GET';
        $sendData = [];
        if ($method == 'POST') {
            $query = isset($pageInfo['query']) ? $pageInfo['query'] : '';
            if (is_array($query)) {
                $sendData['form_params'] = $query;
            } else {
                $sendData['body'] = $query;
            }
        }

        if (isset($pageInfo['extra'])) {
            $sendData = array_merge($sendData, $pageInfo['extra']);
        }

        $html = '';
        try {
            $response = self::$instance['client']->request($method, $pageInfo['url'], $sendData);
            $html = $response->getBody();
            $html = @mb_convert_encoding((string)$html, 'UTF-8', 'UTF-8,GBK,GB2312,BIG5,LATIN1');
            $statusCode = $response->getStatusCode();
            if (!in_array($statusCode, [200])) {
                Log::debug('http error:' . $statusCode, $pageInfo);
            }
        } catch (TransferException $e) {
            $html = '';
        } catch (Exception $e) {
            $html = '';
        }
        return $html;
    }
}
