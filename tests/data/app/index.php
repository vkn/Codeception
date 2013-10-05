<?php
if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__.'/../../../autoload.php';

if (file_exists(__DIR__ . '/../sandbox/c3.php')) {
    require __DIR__ . '/../sandbox/c3.php';
} else {
    require __DIR__ . '/../claypit/c3.php';
}

require_once('glue.php');
require_once('data.php');
require_once('controllers.php');
require_once '../../../src/Codeception/Util/Logger.php';
Codeception\Util\Logger::setFile('../../../application.log');

$urls = array(
    '/' => 'index',
    '/info' => 'info',
    '/cookies' => 'cookies',
    '/login' => 'login',
    '/redirect' => 'redirect',
    '/facebook\??.*' => 'facebookController',
    '/form/(field|select|checkbox|file|textarea|hidden|complex|button|radio|select_multiple|empty|popup)(#)?' => 'form'
);

glue::stick($urls);