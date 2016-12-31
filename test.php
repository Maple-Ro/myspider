<?php
require_once "include/init.php";
use Maple\Caches\RedisHelper;

$res = pathinfo('http://php.net/manual/en/function.parse-url.php');
var_dump($res);