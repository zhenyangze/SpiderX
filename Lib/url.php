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
class Url {
    public static function formatUrl() {
        $pageInfo = parse_url($url);
        foreach ($pageInfo as $field => $value) {
            switch ($field) {
            case 'scheme':
                $pageInfo[$field] .= '://';
                break;
            case 'query':
                $pageInfo[$field] = '?' . $pageInfo[$field];
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
}
