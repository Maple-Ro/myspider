<?php
/**
 * phpspider - A PHP Framework For Crawler
 *
 * @package  phpspider
 * @author   Seatle Yang <seatle@foxmail.com>
 */
// 严格开发模式
error_reporting(E_ALL);
ini_set('display_errors', 1);
// 永不超时
ini_set('max_execution_time', 0);
ini_set('default_socket_timeout', -1);
set_time_limit(0);
// 内存限制，如果外面设置的内存比 /etc/php/php-cli.ini 大，就不要设置了
if (intval(ini_get("memory_limit")) < 1024) {
    ini_set('memory_limit', '1024M');
}
if (PHP_SAPI != 'cli') {
    exit("You must run the CLI environment\n");
}
// 设置时区
date_default_timezone_set('Asia/Shanghai');
//核心库目录
define('ROOT_PATH', dirname(dirname(__FILE__)) . '/');
define('PATH_DATA', ROOT_PATH . "data");
define('SRC', ROOT_PATH . "classes/src");
require_once ROOT_PATH . "include/Loader.php";
spl_autoload_register("\\Loader::autoload");
//系统配置
if (file_exists(ROOT_PATH . "config/inc_config.php")) {
    require ROOT_PATH . "config/inc_config.php";
}

use Maple\Utils\Utils;

// 启动的时候生成data目录
Utils::pathExists(PATH_DATA);
Utils::pathExists(PATH_DATA . "/lock");
Utils::pathExists(PATH_DATA . "/log");
Utils::pathExists(PATH_DATA . "/cache");
Utils::pathExists(PATH_DATA . "/status");