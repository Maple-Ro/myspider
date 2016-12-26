<?php
namespace Maple\PhpSpider;

use Maple\Caches\RedisHelper;
use Maple\DatabaseHelper\DatabaseHelper;
use Maple\Exception\SpiderException;
use Maple\HttpHelper\CurlHelper;
use Maple\Utils\Log;
use Maple\Utils\Utils;

/**
 * PhpSpider - A PHP Framework For Crawler
 *
 * @package  phpspider
 * @author   Seatle Yang <seatle@foxmail.com>
 */
class PhpSpider
{
    /**
     * 版本号
     * @var string
     */
    const VERSION = '2.1.1';

    /**
     * 爬虫爬取每个网页的时间间隔,0表示不延时，单位：秒
     */
    const INTERVAL = 0;

    /**
     * 爬虫爬取每个网页的超时时间，单位：秒
     */
    const TIMEOUT = 5;

    /**
     * 爬取失败次数，不想失败重新爬取则设置为0
     */
    const COLLECT_FAILS = 0;

    /**
     * 抽取规则的类型：xpath、jsonpath、regex
     */
    const FIELDS_SELECTOR_TYPE = 'xpath';

    /**
     * 爬虫爬取网页所使用的浏览器类型：android，ios，pc，mobile
     */
    const AGENT_ANDROID = "Mozilla/5.0 (Linux; U; Android 6.0.1;zh_cn; Le X820 Build/FEXCNFN5801507014S) AppleWebKit/537.36 (KHTML, like Gecko)Version/4.0 Chrome/49.0.0.0 Mobile Safari/537.36 EUI Browser/5.8.015S";
    const AGENT_IOS = "Mozilla/5.0 (iPhone; CPU iPhone OS 9_3_3 like Mac OS X) AppleWebKit/601.1.46 (KHTML, like Gecko) Version/9.0 Mobile/13G34 Safari/601.1";
    const AGENT_PC = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.116 Safari/537.36";
    const AGENT_MOBILE = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.116 Safari/537.36";

    /**
     * pid文件的路径及名称
     * @var string
     */
    public static $pidFile = '';

    /**
     * 日志目录，默认在data根目录下
     * @var mixed
     */
    public static $logFile = '';

    /**
     * 运行 status 命令时用于保存结果的文件名
     * @var string
     */
    public static $statisticsFile = '';

    /**
     * 主任务进程ID
     */
    public static $masterPid = 0;

    /**
     * 所有任务进程ID
     */
    public static $taskPids = [];

    /**
     * 当前任务ID
     */
    public static $taskId = 1;

    /**
     * 当前任务进程ID
     */
    public static $taskPid = 1;

    /**
     * 并发任务数
     */
    public static $taskNum = 1;

    /**
     * 任务主进程
     */
    public static $taskMaster = true;

    /**
     * 任务主进程状态
     */
    public static $taskMasterStatus = false;

    /**
     * 是否保存爬虫运行状态
     */
    public static $saveRunningState = false;

    /**
     * HTTP请求的Header
     */
    public static $headers = [];

    /**
     * HTTP请求的Cookie
     */
    public static $cookies = [];

    /**
     * HTTP请求的Cookie，匹配domain
     */
    public static $domainCookies = [];

    /**
     * 试运行
     * 试运行状态下，程序持续三分钟或抓取到30条数据后停止
     */
    public static $testRun = true;

    /**
     * 配置
     */
    public static $configs = [];

    /**
     * 要抓取的URL队列
     * md5(url) => array(
     * 'url'          => '',      // 要爬取的URL
     * 'url_type'     => '',      // 要爬取的URL类型,scan_page、list_page、content_page
     * 'method'       => 'get',   // 默认为"GET"请求, 也支持"POST"请求
     * 'headers'      => [], // 此url的Headers, 可以为空
     * 'params'       => [], // 发送请求时需添加的参数, 可以为空
     * 'context_data' => '',      // 此url附加的数据, 可以为空
     * 'proxy'        => false,   // 是否使用代理
     * 'proxy_auth'   => '',      // 代理验证: {$USER}:{$PASS}
     * 'collect_count'=> 0        // 抓取次数
     * 'collect_fails'=> 0        // 允许抓取失败次数
     * )
     */
    public static $collectQueue = [];

    /**
     * 要抓取的URL数组
     * md5($url) => time()
     */
    public static $collectUrls = [];

    /**
     * 已经抓取过的URL数组
     * md5($url) => time()
     */
    public static $collectedUrls = [];

    /**
     * 爬虫开始时间
     */
    public static $timeStart = 0;

    /**
     * 当前进程采集成功数
     */
    public static $collectSuccess = 0;

    /**
     * 当前进程采集失败数
     */
    public static $collectFailure = 0;

    public static $taskIdLength = 6;
    public static $pidLength = 6;
    public static $memLength = 8;
    public static $urlsLength = 15;
    public static $speedLength = 6;

    /**
     * 提取到的字段数
     */
    public static $fieldsNum = 0;

    public static $exportType = '';
    public static $exportFile = '';
    public static $exportConf = '';
    public static $exportTable = '';

    /**
     * 爬虫初始化时调用, 用来指定一些爬取前的操作
     *
     * @var mixed
     * @access public
     */
    public $onStart = null;

    /**
     * 切换IP代理后，先前请求网页用到的Cookie会被清除，这里可以再次添加
     *
     * @var mixed
     * @access public
     */
    public $onChangeProxy = null;

    /**
     * 判断当前网页是否被反爬虫，需要开发者实现
     *
     * @var mixed
     * @access public
     */
    public $isAntiSpider = null;

    /**
     * 在一个网页下载完成之后调用，主要用来对下载的网页进行处理
     *
     * @var mixed
     * @access public
     */
    public $onDownloadPage = null;

    /**
     * URL属于入口页
     * 在爬取到入口url的内容之后, 添加新的url到待爬队列之前调用
     * 主要用来发现新的待爬url, 并且能给新发现的url附加数据
     *
     * @var mixed
     * @access public
     */
    public $onScanPage = null;

    /**
     * URL属于列表页
     * 在爬取到列表页url的内容之后, 添加新的url到待爬队列之前调用
     * 主要用来发现新的待爬url, 并且能给新发现的url附加数据
     *
     * @var mixed
     * @access public
     */
    public $onListPage = null;

    /**
     * URL属于内容页
     * 在爬取到内容页url的内容之后, 添加新的url到待爬队列之前调用
     * 主要用来发现新的待爬url, 并且能给新发现的url附加数据
     *
     * @var mixed
     * @access public
     */
    public $onContentPage = null;

    /**
     * 在抽取到field内容之后调用, 对其中包含的img标签进行回调处理
     *
     * @var mixed
     * @access public
     */
    public $onHandleImg = null;

    /**
     * 当一个field的内容被抽取到后进行的回调, 在此回调中可以对网页中抽取的内容作进一步处理
     *
     * @var mixed
     * @access public
     */
    public $onExtractField = null;

    /**
     * 在一个网页的所有field抽取完成之后, 可能需要对field进一步处理, 以发布到自己的网站
     *
     * @var mixed
     * @access public
     */
    public $onExtractPage = null;

    /**
     * 如果抓取的页面是一个附件文件，比如图片、视频、二进制文件、apk、ipad、exe
     * 就不去分析他的内容提取field了，提取field只针对HTML
     *
     * @var mixed
     * @access public
     */
    public $onAttachmentFile = null;

    function __construct(array $configs = [])
    {
        // 先打开以显示验证报错内容
        Log::$log_show = true;

//        // 彩蛋
//        $included_files = get_included_files();
//        $content = file_get_contents($included_files[0]);
//        if (!preg_match("#/\* Do NOT delete this comment \*/#", $content) || !preg_match("#/\* 不要删除这段注释 \*/#", $content)) {
//            Log::error("未知错误；请参考文档或寻求技术支持。");
//            exit;
//        }
        //初始化配置
        self::$configs = $configs;
        self::$configs['name'] = self::$configs['name'] ?? 'phpSpider_' . uniqid();
        self::$configs['proxy'] = self::$configs['proxy'] ?? '';
        self::$configs['proxy_auth'] = self::$configs['proxy_auth'] ?? '';
        self::$configs['user_agent'] = self::$configs['user_agent'] ?? self::AGENT_PC;
        self::$configs['interval'] = self::$configs['interval'] ?? self::INTERVAL;
        self::$configs['timeout'] = self::$configs['timeout'] ?? self::TIMEOUT;
        self::$configs['collect_fails'] = self::$configs['collect_fails'] ?? self::COLLECT_FAILS;
        self::$configs['export'] = self::$configs['export'] ?? [];

        // csv、sql、db
        self::$exportType = self::$configs['export']['type'] ?? '';
        self::$exportFile = self::$configs['export']['file'] ?? '';
        self::$exportTable = self::$configs['export']['table'] ?? '';

        // 是否设置了并发任务数，并且大于1
        if (isset(self::$configs['task_num']) && self::$configs['task_num'] > 1) {
            self::$taskNum = self::$configs['task_num'];
        }
        // 是否设置了保留运行状态
        if (isset(self::$configs['save_running_state'])) {
            self::$saveRunningState = self::$configs['save_running_state'];
        }

        // 不同项目的采集以采集名称作为前缀区分
        if (isset($GLOBALS['config']['redis']['prefix'])) {
            $GLOBALS['config']['redis']['prefix'] = $GLOBALS['config']['redis']['prefix'] . '-' . md5(self::$configs['name']);
        }
    }

    /**
     * 添加模拟的浏览器设备名
     * @param string $userAgent
     */
    public function addUserAgent(string $userAgent)
    {
        CurlHelper::setUserAgent($userAgent);
    }

    /**
     * 一般在 on_start 回调函数中调用，用来添加一些HTTP请求的Header
     * @param string $key
     * @param string $value
     */
    public function addHeader(string $key, string $value)
    {
        self::$headers[$key] = $value;
    }

    /**
     * 一般在 onStart 回调函数中调用，用来得到某个域名所附带的某个Cookie
     * @param string $name
     * @param string $domain
     * @return mixed|string
     */
    public function getCookie(string $name, string $domain = '')
    {
        $cookies = empty($domain) ?? self::$domainCookies[$domain];
        return isset($cookies[$name]) ??'';
    }

    /**
     * 一般在 onStart 回调函数中调用，用来得到某个域名所附带的所有Cookie
     * @param string $domain
     * @return array|mixed
     */
    public function getCookies(string $domain = '')
    {
        return empty($domain) ? self::$cookies : self::$domainCookies[$domain];
    }

    /**
     * 一般在onStart回调函数中调用，用来添加一个HTTP请求的Cookie
     * @param $key
     * @param $value
     * @param string $domain
     */
    public function addCookie(string $key, string $value, string $domain = '')
    {
        if (!empty($domain)) {
            self::$domainCookies[$domain][$key] = $value;
        } else {
            self::$cookies[$key] = $value;
        }
    }

    /**
     * @param string $cookies
     * @param string $domain
     */
    public function addCookies(string $cookies, string $domain = '')
    {
        $cookies_arr = explode(";", $cookies);
        foreach ($cookies_arr as $cookie) {
            $cookie_arr = explode("=", $cookie);
            $key = $value = "";
            foreach ($cookie_arr as $k => $v) {
                if ($k == 0) {
                    $key = trim($v);
                } else {
                    $value .= trim(str_replace('"', '', $v));
                }
            }
            $this->addCookie($key, $value, $domain);
        }
    }

    /**
     * 一般在 on_scan_page 和 on_list_page 回调函数中调用，用来往待爬队列中添加url
     * 两个进程同时调用这个方法，传递相同url的时候，就会出现url重复进入队列
     * @param string $url
     * @param array $options
     * @return bool|void
     */
    public function addUrl(string $url, array $options = [])
    {
        // 投递状态
        $status = false;
        $link = [
            'url' => $url,
            'url_type' => '',
            'method' => $options['method']?? 'get',
            'headers' => $options['headers'] ?? self::$headers,
            'params' => $options['params'] ?? [],
            'context_data' => $options['context_data'] ?? '',
            'proxy' => $options['proxy'] ?? self::$configs['proxy'],
            'proxy_auth' => $options['proxy_auth'] ?? self::$configs['proxy_auth'],
            'collect_count' => $options['collect_count'] ?? 0,
            'collect_fails' => $options['collect_fails'] ?? self::$configs['collect_fails'],
        ];

        if (!empty(self::$configs['list_url_regex'])) {
            foreach (self::$configs['list_url_regex'] as $regex) {
                if (preg_match("#{$regex}#i", $url) && !$this->isCollectUrl($url)) {
                    Log::debug(date("H:i:s") . " 发现列表网页：{$url}");
                    $link['url_type'] = 'list_page';
                    $status = $this->queueLeftPush($link);//to queue
                }
            }
        }

        if (!empty(self::$configs['content_url_regex'])) {
            foreach (self::$configs['content_url_regex'] as $regex) {
                if (preg_match("#{$regex}#i", $url) && !$this->isCollectUrl($url)) {
                    Log::debug(date("H:i:s") . " 发现内容网页：{$url}");
                    $link['url_type'] = 'content_page';
                    $status = $this->queueLeftPush($link);
                }
            }
        }

        if (!empty(self::$configs['attachment_url_regex'])) {
            foreach (self::$configs['attachment_url_regex'] as $regex) {
                if (preg_match("#{$regex}#i", $url) && !$this->isCollectUrl($url)) {
                    Log::debug(date("H:i:s") . " 发现网页文件：{$url}");
                    $link['url_type'] = 'attachment_file';
                    $status = $this->queueLeftPush($link);
                }
            }
        }

        if ($status) {
            $msg = "Success process page {$url}\n";
        } else {
            $msg = "URL not match content_url_reg and list_url_reg, {$url}\n";
        }
        Log::add($msg);
    }


    /**
     * 展示启动界面
     * @return void
     */
    public function displayUi()
    {
        $avg = sys_getloadavg();
        foreach ($avg as $k => $v) {
            $avg[$k] = round($v, 2);
        }
        $display_str = "\033[1A\n\033[K-----------------------------\033[47;30m PHPSPIDER \033[0m-----------------------------\n\033[0m";
        $display_str .= 'PHPSpider version:' . self::VERSION . "          PHP version:" . PHP_VERSION . "\n";
        $display_str .= 'start time:' . date('Y-m-d H:i:s', self::$timeStart) . '   run ' . floor((time() - self::$timeStart) / (24 * 60 * 60)) . ' days ' .
            floor(((time() - self::$timeStart) % (24 * 60 * 60)) / (60 * 60)) . " hours " . floor(((time() - self::$timeStart) % (24 * 60 * 60)) / 60) . " minutes   \n";
        $display_str .= 'load average: ' . implode(", ", $avg) . "\n";
        $display_str .= "document: https://doc.phpspider.org\n";
        $display_str .= "-------------------------------\033[47;30m TASKS \033[0m-------------------------------\n";

        $display_str .= "\033[47;30mtaskid\033[0m" . str_pad('', self::$taskIdLength + 2 - strlen('taskid')) .
            "\033[47;30mpid\033[0m" . str_pad('', self::$pidLength + 2 - strlen('pid')) .
            "\033[47;30mmem\033[0m" . str_pad('', self::$memLength + 2 - strlen('mem')) .
            "\033[47;30mcollect success\033[0m" . str_pad('', self::$urlsLength + 2 - strlen('collect succ')) .
            "\033[47;30mcollect fail\033[0m" . str_pad('', self::$urlsLength + 2 - strlen('collect fail')) .
            "\033[47;30mspeed\033[0m" . str_pad('', self::$speedLength + 2 - strlen('speed')) .
            "\n";

        $display_str .= $this->displayProcessUi();

        $display_str .= "---------------------------\033[47;30m COLLECT STATUS \033[0m--------------------------\n";

        $display_str .= "\033[47;30mfind pages\033[0m" . str_pad('', 16 - strlen('find pages')) .
            "\033[47;30mcollected\033[0m" . str_pad('', 14 - strlen('collected')) .
            "\033[47;30mremain\033[0m" . str_pad('', 15 - strlen('remain')) .
            "\033[47;30mqueue\033[0m" . str_pad('', 14 - strlen('queue')) .
            "\033[47;30mfields\033[0m" . str_pad('', 12 - strlen('fields')) .
            "\n";

        $collect = $this->countCollectUrl();
        $collected = $this->countCollectedUrl();
        $remain = $collect - $collected;
        $queue = $this->queueLSize();
        $fields = $this->getFieldsNum();
        $display_str .= str_pad($collect, 16);
        $display_str .= str_pad($collected, 14);
        $display_str .= str_pad($remain, 15);
        $display_str .= str_pad($queue, 14);
        $display_str .= str_pad($fields, 12);
        $display_str .= "\n";

        // 清屏
        $this->clearScreen();
        // 返回到第一行,第一列
        //echo "\033[0;0H";
        $display_str .= "---------------------------------------------------------------------\n";
        $display_str .= "Press Ctrl-C to quit. Start success.\n";
        echo $display_str;

        //if(self::$daemonize)
        //{
        //global $argv;
        //$start_file = $argv[0];
        //echo "Input \"php $start_file stop\" to quit. Start success.\n";
        //}
        //else
        //{
        //echo "Press Ctrl-C to quit. Start success.\n";
        //}
    }

    /**
     * @return string
     */
    public function displayProcessUi()
    {
        $mem = round(memory_get_usage(true) / (1024 * 1024), 2) . "MB";
        $use_time = microtime(true) - self::$timeStart;
        $speed = round((self::$collectSuccess + self::$collectFailure) / $use_time, 2) . "/s";
        $task = array(
            'id' => self::$taskId,
            'pid' => self::$taskPid,
            'mem' => $mem,
            'collect_success' => self::$collectSuccess,
            'collect_fail' => self::$collectFailure,
            'speed' => $speed,
            'status' => true
        );
        // "\033[32;40m [OK] \033[0m"
        $display_str = str_pad($task['id'], self::$taskIdLength + 2) .
            str_pad($task['pid'], self::$pidLength + 2) .
            str_pad($task['mem'], self::$memLength + 2) .
            str_pad($task['collect_success'], self::$urlsLength + 2) .
            str_pad($task['collect_fail'], self::$urlsLength + 2) .
            str_pad($task['speed'], self::$speedLength + 2) .
            "\n";

        for ($i = 2; $i <= self::$taskNum; $i++) {
            $json = Utils::get_file(PATH_DATA . "/status/" . $i);
            if (empty($json)) {
                continue;
            }
            $task = json_decode($json, true);
            if (empty($task)) {
                continue;
            }
            $display_str .= str_pad($task['id'], self::$taskIdLength + 2) .
                str_pad($task['pid'], self::$pidLength + 2) .
                str_pad($task['mem'], self::$memLength + 2) .
                str_pad($task['collect_success'], self::$urlsLength + 2) .
                str_pad($task['collect_fail'], self::$urlsLength + 2) .
                str_pad($task['speed'], self::$speedLength + 2) .
                "\n";
        }

        //echo "\033[9;0H";
        return $display_str;
    }

    public function clearScreen()
    {
        array_map(create_function('$a', 'print chr($a);'), array(27, 91, 72, 27, 91, 50, 74));
    }

    public function start()
    {
        $this->parseCommand();

        // 爬虫开始时间
        self::$timeStart = time();
        // 当前任务ID
        self::$taskId = 1;
        // 当前任务进程ID
        self::$taskPid = function_exists('posix_getpid') ? posix_getpid() : 1;
        // 当前任务是否主任务
        self::$taskMaster = true;

        //--------------------------------------------------------------------------------
        // 运行前验证
        //--------------------------------------------------------------------------------
        // 多任务需要pcntl扩展支持
        if (self::$taskNum > 1) {
            if (!function_exists('pcntl_fork')) {
                Log::error("When the task number greater than 1 need pcntl extension");
                exit; //todo
            }
        }

        // 保存运行状态需要Redis支持
        if (self::$saveRunningState && !RedisHelper::check()) {
            Log::error("Save the running state need Redis support，Error: " . RedisHelper::$error . "\n\nPlease check the configuration file config/inc_config.php\n");
            exit; //todo
        }

        // 多任务需要Redis支持
        if (self::$taskNum > 1 && !RedisHelper::check()) {
            Log::error("Multitasking need Redis support，Error: " . RedisHelper::$error . "\n\nPlease check the configuration file config/inc_config.php\n");
            exit; //todo
        }

        // 验证导出
        $this->authExport();

        // 检查 scan_urls 
        if (empty(self::$configs['scan_urls'])) {
            Log::error("No scan url to start\n");
            exit;
        }

        foreach (self::$configs['scan_urls'] as $url) {
            $parse_url_arr = parse_url($url);
            if (empty($parse_url_arr['host']) || !in_array($parse_url_arr['host'], self::$configs['domains'])) {
                Log::error("Domain of scan_urls (\"{$parse_url_arr['host']}\") does not match the domains of the domain name\n");
                exit;
            }
        }

        Log::$log_show = self::$configs['log_show'] ?? true;
        $file = date('Ymd') . '_' . uniqid();
        Log::$log_file = self::$configs['log_file'] ?? PATH_DATA . "/$file.Log";

        if (Log::$log_show) {
            Log::info("\n[" . self::$configs['name'] . "爬虫] 开始爬行...\n");
            Log::warn("!开发文档：\n https://doc.phpspider.org\n");
            Log::warn("爬虫任务数：" . self::$taskNum . "\n");
        }

        if ($this->onStart) {
            call_user_func($this->onStart, $this);
        }

        $status_files = scandir(PATH_DATA . "/status");
        foreach ($status_files as $v) {
            if ($v == '.' || $v == '..') {
                continue;
            }
            $filepath = PATH_DATA . "/status/" . $v;
            @unlink($filepath);
        }

        //--------------------------------------------------------------------------------
        // 生成多任务
        //--------------------------------------------------------------------------------
        if (self::$taskNum > 1) {
            //task进程从1开始，0被master进程所使用
            for ($i = 2; $i <= self::$taskNum; $i++) {
                $this->forkOneTask($i);
                Log::info("\n[" . "开启进程{$i}...\n");
            }

            // 不保留运行状态
            if (!self::$saveRunningState) {
                // 清空redis里面的数据
                $this->clear();
            }

            // 设置主任务为未准备状态，堵塞子进程
            $this->setTaskMasterStatus(0);
        }

        foreach (self::$configs['scan_urls'] as $url) {
            $link = [
                'url' => $url,                            // 要抓取的URL
                'url_type' => 'scan_page',                     // 要抓取的URL类型
                'method' => 'get',                           // 默认为"GET"请求, 也支持"POST"请求
                'headers' => self::$headers,                  // 此url的Headers, 可以为空
                'params' => [],                         // 发送请求时需添加的参数, 可以为空
                'context_data' => '',                              // 此url附加的数据, 可以为空
                'proxy' => self::$configs['proxy'],         // 代理服务器
                'proxy_auth' => self::$configs['proxy_auth'],    // 代理验证
                'collect_count' => 0,                               // 抓取次数
                'collect_fails' => self::$configs['collect_fails'], // 允许抓取失败次数
            ];
            $this->queueLeftPush($link);
        }

        while ($this->queueLSize()) {
            // 抓取页面
            $this->collectPage();

            // 多任务下主任务未准备就绪
            if (self::$taskNum > 1 && !self::$taskMasterStatus) {
                // 如果队列中的网页比任务数多，设置主进程为准备好状态，子任务开始采集
                if ($this->queueLSize() > self::$taskNum) {
                    Log::warn("主任务准备就绪...\n");
                    // 给主任务自己用的
                    self::$taskMasterStatus = true;
                    // 给子任务判断用的
                    $this->setTaskMasterStatus(1);
                }
            }

            // 如果不显示日志，就显示控制面板
            if (!Log::$log_show) {
                $this->displayUi();
            }
        }

        Log::info("爬取完成\n");

        $spider_time_run = Utils::time2second(intval(microtime(true) - self::$timeStart));
        Log::info("爬虫运行时间：{$spider_time_run}\n");

        $count_collected_url = $this->countCollectedUrl();
        Log::info("总共抓取网页：{$count_collected_url} 个\n\n");

        if (self::$taskNum > 1) {
            $this->setTaskMasterStatus(1);
        }

        // 最后:多任务下不保留运行状态，清空redis数据
        if (self::$taskNum > 1 && !self::$saveRunningState) {
            $this->clear();
        }
    }

    /**
     * 验证导出
     *
     * @return void
     * @author seatle <seatle@foxmail.com>
     * @created time :2016-10-02 23:37
     */
    public function authExport()
    {
        // 如果设置了导出选项
        if (!empty(self::$configs['export'])) {
            if (self::$exportType == 'csv') {
                if (empty(self::$exportFile)) {
                    Log::error("Export data into CSV files need to Set the file path.");
                    exit; //TODO
                }
            } elseif (self::$exportType == 'sql') {
                if (empty(self::$exportFile)) {
                    Log::error("Export data into SQL files need to Set the file path.");
                    exit;//TODO
                }
            } elseif (self::$exportType == 'db') {
                if (!function_exists('mysqli_connect')) {
                    Log::error("Export data to a database need Mysql support，Error: Unable to load mysqli extension.\n");
                    exit; //TODO
                }

                if (empty($GLOBALS['config']['db'])) {
                    Log::error("Export data to a database need Mysql support，Error: You not set a config array for connect.
                    \n\nPlease check the configuration file config/inc_config.php");
                    exit; //TODO
                }

                $config = $GLOBALS['config']['db'];
                @mysqli_connect($config['host'], $config['user'], $config['pass'], $config['name'], $config['port']);
                if (mysqli_connect_errno()) {
                    Log::error("Export data to a database need Mysql support，Error: " . mysqli_connect_error() . " 
                    \n\nPlease check the configuration file config/inc_config.php");
                    exit; //TODO
                }

                if (!DatabaseHelper::tableExists(self::$exportTable)) {
                    Log::error("Table " . self::$exportTable . " does not exist\n");
                    exit; //TODO
                }
            }
        }
    }

    /**
     * 爬取页面
     * @return bool
     */
    public function collectPage()
    {
        $count_collect_url = $this->countCollectUrl();
        Log::info(date("H:i:s") . " 发现抓取网页：{$count_collect_url} 个\n");

        $queue_lsize = $this->queueLSize();
        Log::info("等待抓取网页：{$queue_lsize} 个\n");

        $count_collected_url = $this->countCollectedUrl();
        Log::info("已经抓取网页：{$count_collected_url} 个\n");

        // 先进先出
        $link = $this->queueRPop();
        $url = $link['url'];

        // 标记为已爬取网页
        $this->setCollectedUrl($url);

        // 爬取页面开始时间
        $page_time_start = microtime(true);

        if ($link['url_type'] == 'attachment_file') {
            if ($this->onAttachmentFile) {
                $pathinfo = pathinfo($url);
                $filetype = $pathinfo['extension'] ?? '';
                call_user_func($this->onAttachmentFile, $url, $filetype, $this);
            }
            return true;
        }

        $html = $this->requestUrl($url, $link);
        if (!$html) {
            return false; //todo
        }

        if ($this->isAntiSpider) {
            $is_anti_spider = call_user_func($this->isAntiSpider, $url, $html);
            // 如果在回调函数里面判断被反爬虫并且返回true
            if ($is_anti_spider) {
                return false; //todo
            }
        }

        // 当前正在爬取的网页页面的对象
        $page = [
            'url' => $url,
            'raw' => $html,
            'request' => [
                'url' => $url,
                'method' => $link['method'],
                'headers' => $link['headers'],
                'params' => $link['params'],
                'context_data' => $link['context_data'],
                'collect_count' => $link['collect_count'],
                'collect_fails' => $link['collect_fails']
            ]
        ];

        // 在一个网页下载完成之后调用. 主要用来对下载的网页进行处理.
        if ($this->onDownloadPage) {
            // 在一个网页下载完成之后调用. 主要用来对下载的网页进行处理
            // 比如下载了某个网页，希望向网页的body中添加html标签
            // 回调函数记得无论如何最后一定要 return $page，因为下面的 入口、列表、内容页回调都用的 $page['raw']
            $page = call_user_func($this->onDownloadPage, $page, $this);
        }

        // 是否从当前页面分析提取URL
        $is_find_url = true;
        if ($link['url_type'] == 'scan_page') {
            if ($this->onScanPage) {
                // 回调函数如果返回false表示不需要再从此网页中发现待爬url
                $is_find_url = call_user_func($this->onScanPage, $page, $page['raw'], $this);
            }
        } elseif ($link['url_type'] == 'list_page') {
            if ($this->onListPage) {
                // 回调函数如果返回false表示不需要再从此网页中发现待爬url
                $is_find_url = call_user_func($this->onListPage, $page, $page['raw'], $this);
            }
        } elseif ($link['url_type'] == 'content_page') {
            if ($this->onContentPage) {
                // 回调函数如果返回false表示不需要再从此网页中发现待爬url
                $is_find_url = call_user_func($this->onContentPage, $page, $page['raw'], $this);
            }
        }

        // 多任务的时候输出爬虫序号
        if (self::$taskNum > 1) {
            Log::info("当前任务序号：" . self::$taskId . "\n");
        }

        // 爬取页面耗时时间
        $time_run = round(microtime(true) - $page_time_start, 3);
        Log::info(date("H:i:s") . " 网页下载成功：{$url}\t耗时: {$time_run} 秒\n");

        $spider_time_run = Utils::time2second(intval(microtime(true) - self::$timeStart));
        Log::info("爬虫运行时间：{$spider_time_run}\n");

        // on_scan_page、on_list_pag、on_content_page 返回false表示不需要再从此网页中发现待爬url
        if ($is_find_url) {
            // 分析提取HTML页面中的URL
            $this->getHtmlUrls($html, $url);
        }

        // 如果是内容页，分析提取HTML页面中的字段
        // 列表页也可以提取数据的，source_type: urlcontext，未实现
        if ($link['url_type'] == 'content_page') {
            $this->getHtmlFields($html, $url, $page);
        }

        // 爬虫爬取每个网页的时间间隔，单位：秒
//        if (!empty(self::$configs['interval'])) {
//            sleep(self::$configs['interval']);
//        } // 默认睡眠100毫秒，太快了会被认为是ddos
//        else {
//            usleep(100000);
//        }
        $interval = self::$configs['interval'] ?? 1;
        sleep($interval);
    }

    /**
     * 下载网页，得到网页内容
     * @param string $url
     * @param array $options
     * @return null|string
     * @throws SpiderException
     */
    public function requestUrl(string $url, $options = [])
    {
        //$url = "http://www.qiushibaike.com/article/117568316";

        $pattern = "/\b(([\w-]+:\/\/?|www[.])[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|\/)))/";
        if (!preg_match($pattern, $url)) {
            throw new SpiderException('你所请求的URL({$url})不是有效的HTTP地址');
        }

        $parse_url_arr = parse_url($url);
        $domain = $parse_url_arr['host'];

        $link = array(
            'url' => $url,
            'url_type' => $options['url_type'] ?? '',
            'method' => $options['method'] ?? 'get',
            'headers' => $options['headers'] ?? self::$headers,
            'params' => $options['params'] ?? [],
            'context_data' => $options['context_data'] ?? '',
            'proxy' => $options['proxy'] ?? self::$configs['proxy'],
            'proxy_auth' => $options['proxy_auth'] ?? self::$configs['proxy_auth'],
            'collect_count' => $options['collect_count'] ?? 0,
            'collect_fails' => $options['collect_fails'] ?? self::$configs['collect_fails'],
        );

        // 如果定义了获取附件回调函数，直接拦截了
        if ($this->onAttachmentFile) {
            $fileInfo = $this->isAttachmentFile($url);
            // 如果不是html
            if (!empty($fileInfo)) {
                Log::debug("发现{$fileInfo['fileext']}文件：{$url}");
                call_user_func($this->onAttachmentFile, $url, $fileInfo);
                return false;
            }
        }

        CurlHelper::setTimeout(self::$configs['timeout']);
        CurlHelper::setUserAgent(self::$configs['user_agent']);

        // 全局Cookie + 域名下的Cookie
        $cookies = self::$cookies;
        if (isset(self::$domainCookies[$domain]) && is_array(self::$domainCookies[$domain])) {
            // 键名为字符时，＋把最先出现的值作为最终结果返回，array_merge()则会覆盖掉前面相同键名的值
            $cookies = array_merge($cookies, self::$domainCookies[$domain]);
        }

        // 是否设置了cookie
        if (!empty($cookies)) {
            foreach ($cookies as $key => $value) {
                $cookie_arr[] = $key . "=" . $value;
            }
            $cookies = implode("; ", $cookie_arr);
            CurlHelper::setCookie($cookies);
        }

        // 是否设置了代理
        if (!empty($link['proxy'])) {
            CurlHelper::setProxy($link['proxy'], $link['proxy_auth']);
            // 自动切换IP
            CurlHelper::setHeaders(array('Proxy-Switch-Ip: yes'));
        }

        // 如何设置了 HTTP Headers
        if (!empty($link['headers'])) {
            CurlHelper::setHeaders($link['headers']);
        }

        // 不能通过 curl_setopt($ch, CURLOPT_NOBODY, 1) 只获取HTTP Header
        // 因为POST数据会失效
        // 即想POST过去，返回的http又只想取header部分是不行的
        CurlHelper::setHttpRaw(true);

        // 如果设置了附加的数据，如json和xml，就直接发附加的数据,php端可以用 file_get_contents("php://input"); 获取
        $params = $link['context_data'] ?? $link['params'];
        $method = strtolower($link['method']);
        $html = CurlHelper::$method($url, $params);
        //var_dump($html);exit;

        // 对于登录成功后302跳转的，Cookie实际上存在body而不在header，header只有一句：HTTP/1.1 100 Continue
        // 为了兼容301和301这些乱七八糟的，还是header+body一起匹配吧
        // 解析Cookie并存入 self::$cookies 方便调用
        preg_match_all("/.*?Set\-Cookie: ([^\r\n]*)/i", $html, $matches);
        $cookies = $matches[1] ?? [];

        // 解析到Cookie
        if (!empty($cookies)) {
            $cookies = implode(";", $cookies);
            $cookies = explode(";", $cookies);
            foreach ($cookies as $cookie) {
                $cookie_arr = explode("=", $cookie);
                // 过滤 httponly、secure
                if (count($cookie_arr) < 2) {
                    continue;
                }
                $cookie_name = !empty($cookie_arr[0]) ? trim($cookie_arr[0]) : '';
                if (empty($cookie_name)) {
                    continue;
                }
                // 过滤掉domain路径
                if (in_array(strtolower($cookie_name), ['path', 'domain', 'expires', 'max-age'])) {
                    continue;
                }
                // 从URL得到的Cookie不要放入全局，放到对应的域名下即可
                //self::$cookies[trim($cookie_arr[0])] = trim($cookie_arr[1]);
                self::$domainCookies[$domain][trim($cookie_arr[0])] = trim($cookie_arr[1]);
            }
        }

        $http_code = CurlHelper::getHttpCode();

        if ($http_code !== 200) {
            switch ($http_code) {
                // 如果是301、302跳转，抓取跳转后的网页内容
                case 301:
                case 302:
                    $info = CurlHelper::getInfo();
                    $url = $info['redirect_url'];
                    $html = $this->requestUrl($url, $options);
                    // 获取跳转后的地址扔到队列头部去，可以立刻采集
                    //$info = cls_curl::get_info();
                    //$link['url'] = $info['redirect_url'];
                    //$this->queue_rpush($link);
                    //$this->Log("网页下载失败：{$url}\n", 'error');
                    //$this->Log("HTTP CODE：{$http_code} 网页{$http_code}跳转\n", 'warn');
                    break;
                case 404:
                    Log::error(date("H:i:s") . " 网页下载失败：{$url}\n");
                    Log::error(date("H:i:s") . " HTTP CODE：{$http_code} 网页不存在\n");
                    break;
                case 407:
                    // 扔到队列头部去，继续采集
                    $this->queueRightPush($link);
                    Log::error(date("H:i:s") . " 网页下载失败：{$url}\n");
                    Log::error(date("H:i:s") . " 代理服务器验证失败，请检查代理服务器设置\n");
                    break;
                case 0:
                case 502:
                case 503:
                    // 采集次数加一
                    $link['collect_count']++;
                    // 抓取次数 小于 允许抓取失败次数
                    if ($link['collect_count'] < $link['collect_fails']) {
                        // 扔到队列头部去，继续采集
                        $this->queueRightPush($link);
                    }
                    Log::error(date("H:i:s") . " 网页下载失败：{$url} 失败次数：{$link['collect_count']}\n");
                    Log::error(date("H:i:s") . " HTTP CODE：{$http_code} 服务器过载\n");
                    break;
                default:
                    Log::error(date("H:i:s") . " 网页下载失败：{$url}\n");
                    Log::error(date("H:i:s") . " HTTP CODE：{$http_code}\n");
                    break;
            }
            self::$collectFailure++;
            return null; //TODO think more!!!
        }
        self::$collectSuccess++;
        //print_r(self::$domain_cookies);

        // 解析HTTP数据流
        if (!empty($html)) {
            // body里面可能有 \r\n\r\n，但是第一个一定是HTTP Header，去掉后剩下的就是body
            $html_arr = explode("\r\n\r\n", $html);
            foreach ($html_arr as $k => $html) {
                // post 方法会有两个http header：HTTP/1.1 100 Continue、HTTP/1.1 200 OK
                if (preg_match("#HTTP/.*? 100 Continue#", $html) || preg_match("#HTTP/.*? 200 OK#", $html)) {
                    unset($html_arr[$k]);
                }
            }
            $html = implode("\r\n\r\n", $html_arr);
        }
        return $html;
    }

    /**
     * 判断是否附件文件，并返回文件信息
     * @param string $url
     * @return array
     */
    public function isAttachmentFile(string $url): array
    {
        $mime_types = $GLOBALS['config']['mimetype'];
        $mime_types_flip = array_flip($mime_types);

        $pathinfo = pathinfo($url);
        $fileext = isset($pathinfo['extension']) ? $pathinfo['extension'] : '';

        $fileinfo = [];
        // 存在文件后缀并且是配置里面的后缀
        if (!empty($fileext) && isset($mime_types_flip[$fileext])) {
            stream_context_set_default(
                [
                    'http' => [
                        'method' => 'HEAD'
                    ]
                ]
            );
            // 代理和Cookie以后实现，方法和 file_get_contents 一样 使用 stream_context_create 设置
            $headers = get_headers($url, 1);
            if (strpos($headers[0], '302')) {
                $url = $headers['Location'];
                $headers = get_headers($url, 1);
            }
            //print_r($headers);
            $fileinfo = [
                'basename' => $pathinfo['basename'] ?? '',
                'filename' => $pathinfo['filename'] ?? '',
                'fileext' => $pathinfo['extension'] ?? '',
                'filesize' => $headers['Content-Length'] ?? 0,
                'atime' => isset($headers['Date']) ? strtotime($headers['Date']) : time(),
                'mtime' => isset($headers['Last-Modified']) ? strtotime($headers['Last-Modified']) : time(),
            ];

            $mime_type = 'html';
            $content_type = $headers['Content-Type'] ?? '';
            if (!empty($content_type)) {
                $mime_type = $GLOBALS['config']['mimetype'][$content_type] ?? $mime_type;
            }
            $mime_types_flip = array_flip($mime_types);
            // 判断一下是不是文件名被加什么后缀了，比如 http://www.xxxx.com/test.jpg?token=xxxxx
            if (!isset($mime_types_flip[$fileinfo['fileext']])) {
                $fileinfo['fileext'] = $mime_type;
                $fileinfo['basename'] = $fileinfo['filename'] . '.' . $mime_type;
            }
        }
        return $fileinfo;
    }

    /**
     * 分析提取HTML页面中的URL
     * @param string $html
     * @param string $collect_url
     * @throws SpiderException
     */
    public function getHtmlUrls(string $html, string $collect_url)
    {
        //--------------------------------------------------------------------------------
        // 正则匹配出页面中的URL
        //--------------------------------------------------------------------------------
        preg_match_all('/<a .*?href="(.*?)".*?>/is', $html, $matchs);
        $urls = !empty($matchs[1]) ? $matchs[1] : [];

        //--------------------------------------------------------------------------------
        // 过滤和拼凑URL
        //--------------------------------------------------------------------------------
        // 去除重复的URL
        $urls = array_unique($urls);
        foreach ($urls as $k => $url) {
            $val = $this->getCompleteUrl($url, $collect_url);
            if ($val) {
                $urls[$k] = $val;
            } else {
                unset($urls[$k]);
            }
        }

        if (empty($urls)) {
            throw new SpiderException($urls . ' is empty!'); //TODO
        }

        //--------------------------------------------------------------------------------
        // 把抓取到的URL放入队列
        //--------------------------------------------------------------------------------
        foreach ($urls as $url) {
            $this->addUrl($url);
        }
    }

    /**
     * 获得主进程准备状态
     * @return bool|null|string
     */
    public function getTaskMasterStatus()
    {
        return RedisHelper::get("taskmaster_ready");
    }

    /**
     * 设置主进程准备状态
     * @param $status
     */
    public function setTaskMasterStatus($status)
    {
        RedisHelper::set("taskmaster_ready", $status);
    }

    /**
     * 清空Redis里面上次爬取的采集数据
     *
     * @return void
     * @author seatle <seatle@foxmail.com>
     * @created time :2016-09-29 13:00
     */
    public function clear()
    {
        // 删除队列
        RedisHelper::del("collect_queue");
        // 删除采集到的field数量
        RedisHelper::del("fields_num");
        // 删除等待采集网页缓存
        $keys = RedisHelper::keysArray("collect_urls-*");
        foreach ($keys as $key) {
            $key = str_replace($GLOBALS['config']['redis']['prefix'] . ":", "", $key);
            RedisHelper::del($key);
        }
        // 删除已经采集网页缓存
        $keys = RedisHelper::keysArray("collected_urls-*");
        foreach ($keys as $key) {
            $key = str_replace($GLOBALS['config']['redis']['prefix'] . ":", "", $key);
            RedisHelper::del($key);
        }
    }

    /**
     * 是否为待爬取网页
     * @param string $url
     * @return bool
     */
    public function isCollectUrl(string $url): bool
    {
        // 多任务 或者 单任务但是从上次继续执行
        if (self::$taskNum > 1 || self::$saveRunningState) {
            $lock = "lock-collect_urls-" . md5($url);
            // 如果不能上锁，说明同时有一个进程带了一样的URL进来判断，而且刚好比这个进程快一丢丢
            // 那么这个进程的URL就可以直接过滤了
            if (!RedisHelper::setNx($lock, "lock")) {
                return true;
            } else {
                // 删除锁然后判断一下这个连接是不是已经在队列里面了
                RedisHelper::del($lock);
                return RedisHelper::exists("collect_urls-" . md5($url));
            }
        } else {
            return array_key_exists(md5($url), self::$collectUrls);
        }
    }

    /**
     * 添加发现网页标记
     * @param string $url
     */
    public function setCollectUrl(string $url)
    {
        // 多任务 或者 单任务但是从上次继续执行
        if (self::$taskNum > 1 || self::$saveRunningState) {
            RedisHelper::set("collect_urls-" . md5($url), time());
        } else {
            self::$collectUrls[md5($url)] = time();
        }
    }

    /**
     * 删除发现网页标记
     * @param string $url
     */
    public function delCollectUrl(string $url)
    {
        // 多任务 或者 单任务但是从上次继续执行
        if (self::$taskNum > 1 || self::$saveRunningState) {
            RedisHelper::del("collect_urls-" . md5($url));
        } else {
            unset(self::$collectUrls[md5($url)]);
        }
    }

    /**
     * 统计爬取网页数量
     * @return int
     */
    public function countCollectUrl()
    {
        // 多任务 或者 单任务但是从上次继续执行
        if (self::$taskNum > 1 || self::$saveRunningState) {
            $count = count(RedisHelper::keysArray("collect_urls-*"));
        } else {
            $count = count(self::$collectUrls);
        }
        return $count;
    }

    /**
     * 等待爬取网页数量
     * @return int
     */
    public function countCollectedUrl(): int
    {
        // 多任务 或者 单任务但是从上次继续执行
        if (self::$taskNum > 1 || self::$saveRunningState) {
            $keys = RedisHelper::keysArray("collected_urls-*");
            $count = count($keys);
        } else {
            $count = count(self::$collectedUrls);
        }
        return $count;
    }

    /**
     * 是否已爬取网页
     * @param $url
     * @return bool
     */
    public function isCollectedUrl(string $url): bool
    {
        // 多任务 或者 单任务但是从上次继续执行
        if (self::$taskNum > 1 || self::$saveRunningState) {
            return RedisHelper::exists("collected_urls-" . md5($url));
        } else {
            return array_key_exists(md5($url), self::$collectedUrls);
        }
    }

    /**
     * 添加已爬取网页标记
     * @param string $url
     */
    public function setCollectedUrl(string $url)
    {
        // 多任务 或者 单任务但是从上次继续执行
        if (self::$taskNum > 1 || self::$saveRunningState) {
            RedisHelper::set("collected_urls-" . md5($url), time());
        } else {
            self::$collectedUrls[md5($url)] = time();
        }
    }

    /**
     * 删除已爬取网页标记
     * @param string $url
     */
    public function delCollectedUrl(string $url)
    {
        // 多任务 或者 单任务但是从上次继续执行
        if (self::$taskNum > 1 || self::$saveRunningState) {
            RedisHelper::del("collected_urls-" . md5($url));
        } else {
            unset(self::$collectedUrls[md5($url)]);
        }
    }

    /**
     * 从队列左边插入新的url数据
     * @param array $link
     * @return bool
     */
    public function queueLeftPush(array $link = [])
    {
        if (empty($link) || empty($link['url'])) {
            return false;
        }

        $url = $link['url'];
        // 先标记为待爬取网页，再入爬取队列
        $this->setCollectUrl($url);
        // 多任务 或者 单任务但是从上次继续执行
        if (self::$taskNum > 1 || self::$saveRunningState) {
            RedisHelper::lPush("collect_queue", json_encode($link));
        } else {
            array_push(self::$collectQueue, $link);
        }
        return true;
    }

    /**
     * 从队列右边插入
     * @param array $link
     * @return bool
     */
    public function queueRightPush(array $link = [])
    {
        if (empty($link) || empty($link['url'])) {
            return false;
        }

        $url = $link['url'];
        // 先标记为待爬取网页，再入爬取队列
        $this->setCollectUrl($url);
        // 多任务 或者 单任务但是从上次继续执行
        if (self::$taskNum > 1 || self::$saveRunningState) {
            $link = json_encode($link);
            RedisHelper::rPush("collect_queue", $link);
        } else {
            array_unshift(self::$collectQueue, $link);
        }
        return true;
    }

    /**
     * 从队列左边取出
     * 后进先出
     * 可以避免采集内容页有分页的时候采集失败数据拼凑不全
     * 还可以按顺序采集列表页
     * @return mixed|string
     */
    public function queueLeftPop()
    {
        // 多任务 或者 单任务但是从上次继续执行
        if (self::$taskNum > 1 || self::$saveRunningState) {
            $link = json_decode(RedisHelper::lPop("collect_queue"), true);
        } else {
            $link = array_pop(self::$collectQueue);
        }
        return $link;
    }

    /**
     * 从队列右边取出link
     * @return mixed|string
     */
    public function queueRPop()
    {
        // 多任务 或者 单任务但是从上次继续执行
        if (self::$taskNum > 1 || self::$saveRunningState) {
            $link = RedisHelper::rpop("collect_queue");
            $link = json_decode($link, true);
        } else {
            $link = array_shift(self::$collectQueue);
        }
        return $link;
    }

    /**
     * 队列长度
     * @return int
     */
    public function queueLSize(): int
    {
        // 多任务 或者 单任务但是从上次继续执行
        if (self::$taskNum > 1 || self::$saveRunningState) {
            $size = RedisHelper::lsize("collect_queue");
        } else {
            $size = count(self::$collectQueue);
        }
        return $size;
    }

    /**
     * 提取到的field数目加一
     * @return int
     */
    public function increaseFieldsNum(): int
    {
        // 多任务 或者 单任务但是从上次继续执行
        if (self::$taskNum > 1 || self::$saveRunningState) {
            $num = RedisHelper::incr("fields_num");
        } else {
            self::$fieldsNum++;
            $num = self::$fieldsNum;
        }
        return $num;
    }

    /**
     * 提取到的field数目
     * @return bool|int|null|string
     */
    public function getFieldsNum()
    {
        // 多任务 或者 单任务但是从上次继续执行
        if (self::$taskNum > 1 || self::$saveRunningState) {
            $num = RedisHelper::get("fields_num");
        } else {
            $num = self::$fieldsNum;
        }
        return $num;
    }

    /**
     * 获得完整的连接地址
     * @param string $url
     * @param string $collect_url
     * @return string
     * @throws \Exception
     */
    public function getCompleteUrl(string $url, string $collect_url)
    {
        $collect_parse_url = parse_url($collect_url);

        // 排除JavaScript的连接
        if (strpos($url, "javascript:") !== false) {
            throw new \Exception('invalid url: ' . $url);
        }

        $cur_parse_url = parse_url($url);

        if (empty($cur_parse_url['path'])) {
            throw new \Exception($url . "'s path is empty");
        }

        // 如果host不为空，判断是不是要爬取的域名
        if (!empty($cur_parse_url['host'])) {
            // 排除非域名下的url以提高爬取速度
            if (!in_array($cur_parse_url['host'], self::$configs['domains'])) {
                throw new \Exception($url . " is not target domain's url");
            }
        } else {
            $url = $collect_parse_url['scheme'] . '://' . str_replace("//", "/", $collect_parse_url['host'] . "/" . $url);
        }
        return $url;
    }

    /**
     * 分析提取HTML页面中的字段
     * @param string $html
     * @param string $url
     * @param int $page
     */
    public function getHtmlFields(string $html, string $url, int $page)
    {
        $fields = $this->getFields(self::$configs['fields'], $html, $url, $page);

        if (!empty($fields)) {
            if ($this->onExtractPage) {
                $return_data = call_user_func($this->onExtractPage, $page, $fields);
                if (!isset($return_data)) {
                    Log::warn("on_extract_page函数返回为空\n");
                } elseif (!is_array($return_data)) {
                    Log::warn("on_extract_page函数返回值必须是数组\n");
                } else {
                    $fields = $return_data;
                }
            }

            if (isset($fields) && is_array($fields)) {
                $fields_num = $this->increaseFieldsNum();
                Log::info(date("H:i:s") . " 结果{$fields_num}：" . json_encode($fields, JSON_UNESCAPED_UNICODE) . "\n");

                // 如果设置了导出选项
                if (!empty(self::$configs['export'])) {
                    self::$exportType = isset(self::$configs['export']['type']) ? self::$configs['export']['type'] : '';
                    if (self::$exportType == 'csv') {
                        Utils::put_file(self::$exportFile, Utils::format_csv($fields) . "\n", FILE_APPEND);
                    } elseif (self::$exportType == 'sql') {
                        $sql = DatabaseHelper::insert(self::$exportTable, $fields, true);
                        Utils::put_file(self::$exportFile, $sql . ";\n", FILE_APPEND);
                    } elseif (self::$exportType == 'db') {
                        DatabaseHelper::insert(self::$exportTable, $fields);
                    }
                }
            }

        }
    }

    /**
     * 根据配置提取HTML代码块中的字段
     * @param array $configs
     * @param string $html
     * @param string $url
     * @param int $page
     * @return array
     * @throws SpiderException
     */
    public function getFields(array $configs, string $html, string $url, int $page): array
    {
        $fields = [];
        foreach ($configs as $conf) {
            // 当前field抽取到的内容是否是有多项
            $repeated = $conf['repeated'] ?? false;
            // 当前field抽取到的内容是否必须有值
            $required = $conf['required'] ?? false;

            if (empty($conf['name'])) {
                throw new SpiderException("field的名字是空值, 请检查你的\"fields\"并添加field的名字\n");
            }

            $values = [];
            // 如果定义抽取规则
            if (!empty($conf['selector'])) {
                // 如果这个field是上一个field的附带连接
                if (isset($conf['source_type']) && $conf['source_type'] == 'attached_url') {
                    // 取出上个field的内容作为连接，内容分页是不进队列直接下载网页的
                    if (!empty($fields[$conf['attached_url']])) {
                        $collect_url = $this->getCompleteUrl($url, $fields[$conf['attached_url']]);
                        Log::info(date("H:i:s") . " 发现内容分页：{$url}");
                        $html = $this->requestUrl($collect_url);
                        // 请求获取完分页数据后把连接删除了 
                        unset($fields[$conf['attached_url']]);
                    }
                }

                // 没有设置抽取规则的类型 或者 设置为 xpath
                if (!isset($conf['selector_type']) || $conf['selector_type'] == 'xpath') {
                    // 返回值一定是多项的
                    $values = $this->getFieldsXpath($html, $conf['selector'], $conf['name']);
                } elseif ($conf['selector_type'] == 'regex') {
                    $values = $this->getFieldsRegex($html, $conf['selector'], $conf['name']);
                }

                // field不为空而且存在子配置
                if (!empty($values) && !empty($conf['children'])) {
                    $child_values = [];
                    // 父项抽取到的html作为子项的提取内容
                    foreach ($values as $html) {
                        // 递归调用本方法，所以多少子项目都支持
                        $child_value = $this->getFields($conf['children'], $url, $html, $page);
                        if (!empty($child_value)) {
                            $child_values[] = $child_value;
                        }
                    }
                    // 有子项就存子项的数组，没有就存HTML代码块
                    if (!empty($child_values)) {
                        $values = $child_values;
                    }
                }
            }

            if (empty($values)) {
                // 如果值为空而且值设置为必须项，跳出foreach循环
                if ($required) {
                    break;
                }
                // 避免内容分页时attached_url拼接时候string + array了
                $fields[$conf['name']] = '';
                //$fields[$conf['name']] = [];
            } else {
                // 不重复抽取则只取第一个元素
                $fields[$conf['name']] = $repeated ? $values : $values[0];
            }
        }

        if (!empty($fields)) {
            foreach ($fields as $fieldname => $data) {
                $pattern = "/<img.*?src=[\'|\"](.*?(?:[\.gif|\.jpg|\.jpeg|\.png]))[\'|\"].*?[\/]?>/i";
                // 在抽取到field内容之后调用, 对其中包含的img标签进行回调处理
                if ($this->onHandleImg && preg_match($pattern, $data)) {
                    $return = call_user_func($this->onHandleImg, $fieldname, $data);
                    if (!isset($return)) {
                        Log::warn("on_handle_img函数返回为空\n");
                    } else {
                        // 有数据才会执行 on_handle_img 方法，所以这里不要被替换没了
                        $data = $return;
                    }
                }

                // 当一个field的内容被抽取到后进行的回调, 在此回调中可以对网页中抽取的内容作进一步处理
                if ($this->onExtractField) {
                    $return = call_user_func($this->onExtractField, $fieldname, $data, $page, self::$taskId);
                    if (!isset($return)) {
                        Log::warn("on_extract_field函数返回为空\n");
                    } else {
                        // 有数据才会执行 on_extract_field 方法，所以这里不要被替换没了
                        $fields[$fieldname] = $return;
                    }
                }
            }
        }
        return $fields;
    }

    /**
     * 采用xpath分析提取字段
     * @param string $html
     * @param string $selector
     * @param string $fieldname
     * @return array
     */
    public function getFieldsXpath(string $html, string $selector, string $fieldname)
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        //libxml_use_internal_errors(true);
        //$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        //$errors = libxml_get_errors();
        //if (!empty($errors)) 
        //{
        //print_r($errors);
        //exit;
        //}

        $xpath = new \DOMXpath($dom);
        $elements = @$xpath->query($selector);
        if ($elements === false) {
            Log::error("field(\"{$fieldname}\")中selector的xpath(\"{$selector}\")语法错误\n");
            exit;
        }

        $array = [];
        if (!is_null($elements)) {
            foreach ($elements as $element) {
                $nodeName = $element->nodeName;
                $nodeType = $element->nodeType;     // 1.Element 2.Attribute 3.Text
                //$nodeAttr = $element->getAttribute('src');
                //$nodes = util::node_to_array($dom, $element);
                //echo $nodes['@src']."\n";
                // 如果是img标签，直接取src值
                if ($nodeType == 1 && in_array($nodeName, array('img'))) {
                    $content = $element->getAttribute('src');
                } // 如果是标签属性，直接取节点值
                elseif ($nodeType == 2 || $nodeType == 3) {
                    $content = $element->nodeValue;
                } else {
                    // 保留nodeValue里的html符号，给children二次提取
                    $content = $dom->saveXml($element);
                    //$content = trim($dom->saveHtml($element));
                    $content = preg_replace(array("#^<{$nodeName}.*>#isU", "#</{$nodeName}>$#isU"), array('', ''), $content);
                }
                $array[] = trim($content);
            }
        }
        return $array;
    }

    /**
     * 采用正则分析提取字段
     * @param string $html
     * @param string $selector
     * @param string $fieldname
     * @return array
     */
    public function getFieldsRegex(string $html, string $selector, string $fieldname): array
    {
        if (@preg_match_all($selector, $html, $out) === false) {
            Log::error("field(\"{$fieldname}\")中selector的regex(\"{$selector}\")语法错误\n");
            exit;
        }

        $array = [];
        if (!is_null($out[1])) {
            foreach ($out[1] as $v) {
                $array[] = trim($v);
            }
        }
        return $array;
    }

    /**
     * 采用CSS选择器提取字段
     *
     * @param mixed $html
     * @param mixed $selector
     * @param mixed $fieldname
     * @return void
     * @author seatle <seatle@foxmail.com>
     * @created time :2016-09-18 10:17
     */
    public function getFieldsCss($html, $selector, $fieldname)
    {
        //TODO to be finished
    }

    /**
     * TODO REWRITE???
     */
    public function parseCommand()
    {
        // 检查运行命令的参数
        global $argv;
        $start_file = $argv[0];
        if (!isset($argv[1])) {
            exit("Usage: php your file.php {start|stop|status}\n");
        }

        // 命令
        $command = trim($argv[1]);

        // 子命令，目前只支持-d
        $command2 = $argv[2] ?? '';

        //// 检查主进程是否在运行
        //$master_pid = @file_get_contents(self::$pid_file);
        //$master_is_alive = $master_pid && @posix_kill($master_pid, 0);
        //if($master_is_alive)
        //{
        //if($command === 'start')
        //{
        //Log::error("PHPSpider[$start_file] is running");
        //exit;
        //}
        //}
        //elseif($command !== 'start')
        //{
        //Log::error("PHPSpider[$start_file] not run");
        //exit;
        //}

        // 根据命令做相应处理
        switch ($command) {
            // 启动 php spider
            case 'start':
                break;
            // 显示 php spider 运行状态
            case 'status':
                exit(0);
            case 'stop':
                break;
            // 未知命令
            default :
                exit("Usage: php your file.php {start|stop|status}\n");
        }
    }

    public function forkOneTask(int $taskId)
    {
        $pid = pcntl_fork();

        // 主进程记录子进程pid
        if ($pid > 0) {
            self::$taskPids[$taskId] = $pid;
        } // 子进程运行
        elseif (0 === $pid) {
            self::$timeStart = microtime(true);
            self::$collectSuccess = 0;
            self::$collectFailure = 0;
            self::$taskId = $taskId;
            self::$taskMaster = false;
            self::$taskPid = posix_getpid();
            Log::info("任务" . self::$taskPid . "等待中...\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n");
            // sleep 1秒，等待主任务设置状态
            sleep(1);
            // 第一次先判断主进程准备好没有
            while (!$this->getTaskMasterStatus()) {
                Log::warn("任务" . self::$taskId . "等待中...\n");
                sleep(1);
            }

            while ($this->queueLSize()) {
                // 如果队列中的网页比任务数多，子任务可以采集
                if ($this->queueLSize() > self::$taskNum) {
                    // 抓取页面
                    $this->collectPage();
                } // 队列中网页太少，就都给主进程采集好了
                else {
                    Log::warn("任务" . self::$taskId . "等待中...\n");
                    sleep(1);
                }

                // 当前进程状态输出到文件，供主进程调用
                $mem = round(memory_get_usage(true) / (1024 * 1024), 2) . "MB";
                $use_time = microtime(true) - self::$timeStart;
                $speed = round((self::$collectSuccess + self::$collectFailure) / $use_time, 2) . "/s";
                $status = [
                    'id' => self::$taskId,
                    'pid' => self::$taskPid,
                    'mem' => $mem,
                    'collect_success' => self::$collectSuccess,
                    'collect_fail' => self::$collectFailure,
                    'speed' => $speed,
                ];
                Utils::put_file(PATH_DATA . "/status/" . self::$taskId, json_encode($status));
            }

            // 这里用0表示正常退出
            exit(0);
        } else {
            Log::error("fork one worker fail");
            exit;
        }
    }
}

