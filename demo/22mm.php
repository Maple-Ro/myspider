<?php
ini_set("memory_limit", "1024M");
require dirname(__FILE__).'/../core/init.php';

/* Do NOT delete this comment */
/* 不要删除这段注释 */

$configs = [
    'name' => '22',
    'domains' => [
        'www.juemei.com',

    ],
    'collect_fails'=>2,
    'tasknum'	=>	4,
    'save_running_state'	=>	true,
    'scan_urls' =>[
        "http://www.juemei.com/mm/sfz/",
            "http://www.juemei.com/mm/qcmm/",
            "http://www.juemei.com/mm/chemo/",
            "http://www.juemei.com/mm/xiezhen/",
            "http://www.juemei.com/mm/xinggan/",
            "http://www.juemei.com/mm/meitui/"
    ],
    'list_url_regexes' => [
        "http://www.juemei.com/mm/sfz/index_\d+.html",
        "http://www.juemei.com/mm/qcmm/index_\d+.html",
        "http://www.juemei.com/mm/chemo/index_\d+.html",
        "http://www.juemei.com/mm/xiezhen/index_\d+.html",
        "http://www.juemei.com/mm/xinggan/index_\d+.html",
        "http://www.juemei.com/mm/meitui/index_\d+.html",
    ],
    'content_url_regexes' => [
        "http://www.juemei.com/mm/\d{6}/\d{4}.html",
        "http://www.juemei.com/mm/\d{6}/\d{4}_\d{1,2}.html"
    ],
//    'export' => array(
//    'type' => 'csv',
//    'file' => PATH_DATA.'/qiushibaike.csv',
//    ),
//    'export' => array(
//    'type'  => 'sql',
//    'file'  => PATH_DATA.'/13384.sql',
//    'table' => 'content',
//    ),
    'export' => [
        'type' => 'db',
        'table' => 'meinv_content',
    ],
    'fields' => [
        // 标题
        [
            'name' => "name",
            'selector' => "//div[contains(@class, 'album')]//h1",
            'required' => true,
        ],
        // 分类
        [
            'name' => "category",
            'selector' => "//div[contains(@class,'palce')]//a",
            'required' => true,
        ],
        // 发布时间
        [
            'name' => "addtime",
            'selector' => "//span[contains(@class,'date')]//em",
            'required' => true,
        ],
        // 内容
        [
            'name' => "content",
            'selector' => "//div[contains(@class, 'page')]//a//@href", //分页
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
                    'selector' => "//div[contains(@class, 'wrap')]//img"
                ]
            ]
        ]
    ]
];

$spider = new phpspider($configs);

$spider->on_extract_field = function($fieldname, $data, $page) 
{
    if ($fieldname == 'name') 
    {
        $data = trim(preg_replace("#\(.*?\)#", "", $data));
    }
    if ($fieldname == 'addtime') 
    {
        $data = mb_substr($data, 5, 15);
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
