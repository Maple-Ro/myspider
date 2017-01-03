<?php
/**
 * Description:
 * User: Endless
 * Date: 2017/1/1
 * Time: 06:41
 */

use Maple\PhpSpider\PhpSpider;

//include "../../../include/Loader.php";

class PhpSpiderTest extends \PHPUnit_Framework_TestCase
{
    public function testRequest()
    {
        $url = "www.baidu.com";
        $p = new PhpSpider();
        $p->request($url);
    }
}
