<?php
/**
 * Description:
 * User: Endless
 * Date: 2017/4/4
 * Time: 10:36
 */
define('ROOT', dirname(dirname(__FILE__)) . '/');
$loader = require ROOT . '/vendor/autoload.php';
$mapleClassesRoot = ROOT . '/classes/src/';
$loader->addPsr4('Maple\\', $mapleClassesRoot);