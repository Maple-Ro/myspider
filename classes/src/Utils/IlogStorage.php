<?php
/**
 * Description:
 * User: Endless
 * Date: 2017/4/5
 * Time: 00:44
 */

namespace Maple\Utils;


interface IlogStorage
{
    /**
     * @param string $name 日志记录标记
     * @param string $msg 日志信息
     * @return mixed
     */
    function add(string $msg);
}