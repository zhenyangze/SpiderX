<?php
/**
 * Short description for demo1.php
 *
 * @package demo1
 * @author zhenyangze <zhenyangze@gmail.com>
 * @version 0.1
 * @copyright (C) 2018 zhenyangze <zhenyangze@gmail.com>
 * @license MIT
 */

include_once __DIR__ . '/../vendor/autoload.php';
use GuzzleHttp\Client;
$client = new Client([
    'timeout'  => 10.0,
]);
$response = $client->get('http://www.auma.de/en/Messedatenbank/Seiten/MesseDetailSeite.aspx?tf=157929&title=Inter');
echo $response->getBody();
