<?php
/**
 * Description:
 * User: Endless
 * Date: 2017/4/5
 * Time: 00:46
 */

namespace Maple\Utils;


use Maple\Helper\RedisHelper;

/**
 * redis日志存储类，将日志集中为一个集合再存储到redis中
 * Class RedisLog
 * @package Maple\Utils
 */
class RedisLog implements IlogStorage
{
    /**
     * @var RedisHelper
     */
    private $redis;

    function __construct(RedisHelper $redis)
    {
        $this->redis = $redis;
    }

    function add(string $msg)
    {
//        $this->redis->set();
        //使用redis的集合数据结构存储每一条日志

    }
}