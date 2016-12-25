<?php
ini_set("memory_limit", "1024M");
require dirname(__FILE__) . '/../src/init.php';

/* Do NOT delete this comment */
/* 不要删除这段注释 */
$configs = [
    'name' => '13384美女图',
    'domains' => [
        'www.13384.com',
        'tasknum'	=>	2,
        'save_running_state'	=>	true
    ],
    'collect_fails'=>2,
    'scan_urls' =>[
        "http://www.13384.com/qingchunmeinv/",
        //"http://www.13384.com/xingganmeinv/",
        //"http://www.13384.com/mingxingmeinv/",
        //"http://www.13384.com/siwameitui/",
        //"http://www.13384.com/meinvmote/",
        //"http://www.13384.com/weimeixiezhen/",
    ],
    'list_url_regexes' => [
        "http://www.13384.com/qingchunmeinv/index_\d+.html",
        //"http://www.13384.com/xingganmeinv/index_\d+.html",
        //"http://www.13384.com/mingxingmeinv/index_\d+.html",
        //"http://www.13384.com/siwameitui/index_\d+.html",
        //"http://www.13384.com/meinvmote/index_\d+.html",
        //"http://www.13384.com/weimeixiezhen/index_\d+.html",
    ],
    'content_url_regexes' => [
        "http://www.13384.com/qingchunmeinv/\d+.html",
        //"http://www.13384.com/xingganmeinv/\d+.html",
        //"http://www.13384.com/mingxingmeinv/\d+.html",
        //"http://www.13384.com/siwameitui/\d+.html",
        //"http://www.13384.com/meinvmote/\d+.html",
        //"http://www.13384.com/weimeixiezhen/\d+.html",
    ],
    //'export' => array(
    //'type' => 'csv',
    //'file' => PATH_DATA.'/qiushibaike.csv',
    //),
    //'export' => array(
    //'type'  => 'sql',
    //'file'  => PATH_DATA.'/13384.sql',
    //'table' => 'content',
    //),
    'export' => [
        'type' => 'db',
        'table' => 'meinv_content',
    ],
    'fields' => [
        // 标题
        [
            'name' => "name",
            'selector' => "//div[@id='Article']//h1",
            'required' => true,
        ],
        // 分类
        [
            'name' => "category",
            'selector' => "//div[contains(@class,'crumbs')]//span//a",
            'required' => true,
        ],
        // 发布时间
        [
            'name' => "addtime",
            'selector' => "//p[contains(@class,'sub-info')]//span",
            'required' => true,
        ],
        // 内容
        [
            'name' => "content",
            'selector' => "//div[@id='pages']//a//@href",
            'repeated' => true,
            'required' => true,
            'children' => [
                [
                    // 抽取出其他分页的url待用
                    'name' => 'content_page_url',
                    'selector' => "//text()"
                ],
                [
                    // 抽取其他分页的内容
                    'name' => 'page_content',
                    // 发送 attached_url 请求获取其他的分页数据
                    // attached_url 使用了上面抓取的 content_page_url
                    'source_type' => 'attached_url',
                    'attached_url' => 'content_page_url',
                    'selector' => "//*[@id='big-pic']//a//img"
                ]
            ]
        ]
    ]
];

$spider = new PhpSpider($configs);

$spider->on_extract_field = function($fieldname, $data, $page) 
{
    if ($fieldname == 'name') 
    {
        $data = trim(preg_replace("#\(.*?\)#", "", $data));
    }
    if ($fieldname == 'addtime') 
    {
        $data = strtotime(substr($data, 0, 19));
    }
    elseif ($fieldname == 'content') 
    {
        $contents = $data;
        $array = [];
        foreach ($contents as $content) 
        {
            $url = $content['page_content'];
            // 以纳秒为单位生成随机数
            $filename = uniqid().".jpg";
            // 在data目录下生成图片
            $filepath = PATH_DATA."/images/{$filename}";
            // 用系统自带的下载器wget下载
            exec("wget {$url} -O {$filepath}");
            $array[] = $filename;
        }
        $data = implode(",", $array);
    }
    return $data;
};

$spider->start();
