<?php
require_once "include/init.php";
use Maple\Caches\RedisHelper;

$res = RedisHelper::check();
var_dump($res);