<?php
/**
 * Description:爬虫爬取页面记录日志情况
 * 存储方式：1.文件存储，2.redis缓存
 * User: Endless
 * Date: 2017/4/5
 * Time: 00:39
 */

namespace Maple\Utils;
class Log
{
    /**
     * @var IlogStorage 存储实例
     * 可便捷切换不同的日志存储方式
     */
    private $storage;
    private static $instance;

    private function __construct(IlogStorage $storage)
    {
        $this->storage = $storage;
    }

    private function __clone()
    {
        // TODO: Implement __clone() method.
    }

    public static function getInstance(IlogStorage $storage)
    {
        if (isset(self::$instance)) {
            self::$instance = new Log($storage);
        }
        return self::$instance;
    }

    public function info(string $msg)
    {
        $out_sta = "";
        $out_end = "";
        $msg = $out_sta . $msg . $out_end . "\n";
        $this->add($msg);
    }

    public function warn(string $msg)
    {
        $out_sta = "\033[33m";
        $out_end = "\033[0m";
        $msg = $out_sta . $msg . $out_end . "\n";
        $this->add($msg);
    }

    public function debug(string $msg)
    {
        $out_sta = "\033[36m";
        $out_end = "\033[0m";
        $msg = $out_sta . $msg . $out_end . "\n";
        $this->add($msg);
    }

    public function error(string $msg)
    {
        $out_sta = "\033[31m";
        $out_end = "\033[0m";
        //$msg = $out_sta.date("H:i:s")." ".$msg.$out_end."\n";
        $msg = $out_sta . $msg . $out_end . "\n";
        $this->add($msg);
    }

    public function add(string $msg)
    {
        $this->storage->add($msg);
//        file_put_contents(self::$log_file, $msg, FILE_APPEND | LOCK_EX);
    }

//    public  function add($msg, $log_type = '')
//    {
//        if ($log_type != '') {
//            $msg = date("Y-m-d H:i:s") . " [{$log_type}] " . $msg . "\n";
//        }
//        if (self::$log_show) {
//            echo $msg;
//        }
//        //file_put_contents(PATH_DATA."/log/".strtolower($log_type).".log", $msg, FILE_APPEND | LOCK_EX);
//        file_put_contents(PATH_DATA . "/log/error.log", $msg, FILE_APPEND | LOCK_EX);
//    }
}