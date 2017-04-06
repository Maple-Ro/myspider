<?php
/**
 * Description:
 * User: Endless
 * Date: 2017/4/5
 * Time: 00:46
 */

namespace Maple\Utils;


/**
 * 文件日志存储类
 * Class FileLog
 * @package Maple\Utils
 */
class FileLog implements IlogStorage
{

    function add(string $msg)
    {
        $dir = ROOT . 'data/log';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        file_put_contents($dir . '/spider_log.log', $msg, FILE_APPEND | LOCK_EX);
    }
}