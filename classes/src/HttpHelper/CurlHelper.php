<?php
namespace Maple\HttpHelper;
/**
 * Worker多进程操作类
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author seatle<seatle@foxmail.com>
 * @copyright seatle<seatle@foxmail.com>
 * @link http://www.epooll.com/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */
/**-----------------------------------------------------------------  */
/**-- 重构，修改所有的api，不使用静态的方式       */
/**-----------------------------------------------------------------  */
class CurlHelper
{
    protected $userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/44.0.2403.89 Safari/537.36';
    protected $timeout = 10;
    protected $ch = null;
    protected $httpRaw = false;
    protected $cookie = null;
    protected $cookieJar = null;
    protected $cookieFile = null;
    protected $referer = null;
    protected $ip = null;
    protected $proxy = null;
    protected $proxyAuth = null;
    protected $headers = [];
    protected $hosts = [];
    protected $gzip = false;
    protected $info = [];

    public function __construct()
    {
        $this->init();
    }

    private function __clone()
    {
        // TODO: Implement __clone() method.
    }

    private function init()
    {
        //if (empty ( $this->ch ))
        if (!is_resource($this->ch)) {
            $this->ch = curl_init();
            curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);//返回内容为字符串
            curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
            curl_setopt($this->ch, CURLOPT_HEADER, false);
            curl_setopt($this->ch, CURLOPT_USERAGENT, $this->userAgent);
            curl_setopt($this->ch, CURLOPT_TIMEOUT, $this->timeout + 5);
            // 在多线程处理场景下使用超时选项时，会忽略signals对应的处理函数，但是无耐的是还有小概率的crash情况发生
            curl_setopt($this->ch, CURLOPT_NOSIGNAL, true);
        }
        return $this->ch;
    }

    public function get(string $url, $fields = [])
    {
        return $this->request($url, 'get', $fields);
    }

    /**
     * $fields 有三种类型:1、数组；2、http query；3、json
     * 1、array('name'=>'yangzetao') 2、http_build_query(array('name'=>'yangzetao')) 3、json_encode(array('name'=>'yangzetao'))
     * 前两种是普通的post，可以用$_POST方式获取
     * 第三种是post stream( json rpc，其实就是webservice )，虽然是post方式，但是只能用流方式 http://input 后者 $HTTP_RAW_POST_DATA 获取
     * @param $url
     * @param array $fields
     * @return mixed
     */
    public function post(string $url, $fields = [])
    {
        return $this->request($url, 'post', $fields);
    }

    /**
     * 关键方法
     * @param string $url
     * @param string $type
     * @param $fields
     * @return mixed
     */
    private function request(string $url, string $type = 'get', $fields)
    {
        // 如果是 get 方式，直接拼凑一个 url 出来
        if (strtolower($type) == 'get' && !empty($fields)) {
            $url = $url . (strpos($url, "?") === false ? "?" : "&") . http_build_query($fields);
        }

        // 随机绑定 hosts，做负载均衡
        if ($this->hosts) {
            $parse_url = parse_url($url);
            $host = $parse_url['host'];
            $key = rand(0, count($this->hosts) - 1);
            $ip = $this->hosts[$key];
            $url = str_replace($host, $ip, $url);
            $this->headers = array_merge(['Host:' . $host], $this->headers);
        }
        curl_setopt($this->ch, CURLOPT_URL, $url);//
        // 如果是 post 方式
        if (strtolower($type) == 'post') {
            curl_setopt($this->ch, CURLOPT_POST, true);
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $fields);
        }
        if ($this->userAgent) {
            curl_setopt($this->ch, CURLOPT_USERAGENT, $this->userAgent);
        }
        if ($this->cookie) {
            curl_setopt($this->ch, CURLOPT_COOKIE, $this->cookie);
        }
        if ($this->cookieJar) {
            curl_setopt($this->ch, CURLOPT_COOKIEJAR, $this->cookieJar);
        }
        if ($this->cookieFile) {
            curl_setopt($this->ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        }
        if ($this->referer) {
            curl_setopt($this->ch, CURLOPT_REFERER, $this->referer);
        }
        if ($this->ip) {
            $this->headers = array_merge(['CLIENT-IP:' . $this->ip, 'X-FORWARDED-FOR:' . $this->ip], $this->headers);
        }
        if ($this->headers) {
            curl_setopt($this->ch, CURLOPT_HTTPHEADER, $this->headers);
        }
        if ($this->gzip) {
            curl_setopt($this->ch, CURLOPT_ENCODING, $this->gzip);
        }
        if ($this->proxy) {
            curl_setopt($this->ch, CURLOPT_PROXY, $this->proxy);
            if ($this->proxyAuth) {
                curl_setopt($this->ch, CURLOPT_PROXYUSERPWD, $this->proxyAuth);
            }
        }
        if ($this->httpRaw) {
            curl_setopt($this->ch, CURLOPT_HEADER, true);
        }

        $data = curl_exec($this->ch); //获取数据
        $this->info = curl_getinfo($this->ch);
        if ($data === false) {
            //echo date("Y-m-d H:i:s"), ' Curl error: ' . curl_error( $this->ch ), "\n";
        }

        // 关闭句柄
        curl_close($this->ch);
        //$data = substr($data, 10);
        //$data = gzinflate($data);
        return $data;
    }

    public function getInfo()
    {
        return $this->info;
    }

    public function getHttpCode()
    {
        if (!empty($this->info)) return $this->info['http_code'];
    }

    /**
     * set timeout
     *
     * @param int $timeout
     */
    public function setTimeout(int $timeout)
    {
        $this->timeout = $timeout;
    }

    /**
     * 设置代理
     *
     * @param mixed $proxy
     * @param string $proxy_auth
     * @return void
     * @author seatle <seatle@foxmail.com>
     * @created time :2016-09-18 10:17
     */
    public function setProxy(string $proxy, string $proxy_auth = '')
    {
        $this->proxy = $proxy;
        $this->proxyAuth = $proxy_auth;
    }

    /**
     * @param string $referer
     */
    public function setReferer(string $referer)
    {
        $this->referer = $referer;
    }

    /**
     * 设置 user_agent
     *
     * @param string $userAgent
     * @return void
     */
    public function setUserAgent($userAgent)
    {
        $this->userAgent = $userAgent;
    }

    /**
     * 设置COOKIE
     *
     * @param string $cookie
     * @return void
     */
    public function setCookie($cookie)
    {
        $this->cookie = $cookie;
    }

    /**
     * 设置COOKIE JAR
     *
     * @param string $cookieJar
     * @return void
     */
    public function setCookieJar($cookieJar)
    {
        $this->cookieJar = $cookieJar;
    }

    /**
     * 设置COOKIE FILE
     *
     * @param string $cookieFile
     * @return void
     */
    public function setCookieFile($cookieFile)
    {
        $this->cookieFile = $cookieFile;
    }

    /**
     * 获取内容的时候是不是连header也一起获取
     *
     * @param mixed $httpRaw
     * @return void
     * @author seatle <seatle@foxmail.com>
     * @created time :2016-09-18 10:17
     */
    public function setHttpRaw(bool $httpRaw)
    {
        $this->httpRaw = $httpRaw;
    }

    /**
     * 设置IP
     *
     * @param string $ip
     * @return void
     */
    public function setIp($ip)
    {
        $this->ip = $ip;
    }

    /**
     * 设置Headers
     *
     * @param string $headers
     * @return void
     */
    public function setHeaders(string $headers)
    {
        $this->headers = $headers;
    }

    /**
     * 设置Hosts
     *
     * @param string $hosts
     * @return void
     */
    public function setHosts($hosts)
    {
        $this->hosts = $hosts;
    }

    /**
     * 设置Gzip
     * @param string $gzip
     */
    public function setGzip(string $gzip)
    {
        $this->gzip = $gzip;
    }
}

function classic_curl(array $urls, int $delay)
{
    $queue = curl_multi_init();
    $map = [];

    foreach ($urls as $url) {
        // create cURL resources
        $ch = curl_init();

        // 设置 URL 和 其他参数
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_NOSIGNAL, true);

        // 把当前 curl resources 加入到 curl_multi_init 队列
        curl_multi_add_handle($queue, $ch);
        $map[$url] = $ch;
    }

    $active = null;

    // execute the handles
    do {
        $mrc = curl_multi_exec($queue, $active);
    } while ($mrc == CURLM_CALL_MULTI_PERFORM);

    while ($active > 0 && $mrc == CURLM_OK) {
        while (curl_multi_exec($queue, $active) === CURLM_CALL_MULTI_PERFORM) ;
        // 这里 curl_multi_select 一直返回 -1，所以这里就死循环了，CPU就100%了
        if (curl_multi_select($queue, 0.5) != -1) {
            do {
                $mrc = curl_multi_exec($queue, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        }
    }

    $responses = [];
    foreach ($map as $url => $ch) {
        //$responses[$url] = callback(curl_multi_getcontent($ch), $delay);
        $responses[$url] = callback(curl_multi_getcontent($ch), $delay, $url);
        curl_multi_remove_handle($queue, $ch);
        curl_close($ch);
    }

    curl_multi_close($queue);
    return $responses;
}

function rolling_curl($urls, $delay)
{
    $queue = curl_multi_init();
    $map = [];

    foreach ($urls as $url) {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_NOSIGNAL, true);
        $cookie = '_za=36643642-e546-4d60-a771-8af8dcfbd001; q_c1=a57a2b9f10964f909b8d8969febf3ab2|1437705596000|1437705596000; _xsrf=f0304fba4e44e1d008ec308d59bab029; cap_id="YWY1YmRmODlmZGVmNDc3MWJlZGFkZDg3M2E0M2Q5YjM=|1437705596|963518c454bb6f10d96775021c098c84e1e46f5a"; z_c0="QUFCQVgtRWZBQUFYQUFBQVlRSlZUVjR6NEZVUTgtRkdjTVc5UDMwZXRJZFdWZ2JaOWctNVhnPT0=|1438164574|aed6ef3707f246a7b64da4f1e8c089395d77ff2b"; __utma=51854390.1105113342.1437990174.1438160686.1438164116.10; __utmc=51854390; __utmz=51854390.1438134939.8.5.utmcsr=zhihu.com|utmccn=(referral)|utmcmd=referral|utmcct=/people/yangzetao; __utmv=51854390.100-1|2=registration_date=20131030=1^3=entry_date=20131030=1';
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        $useragent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/44.0.2403.89 Safari/537.36';
        curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip');

        curl_multi_add_handle($queue, $ch);
        $map[(string)$ch] = $url;
    }

    $responses = [];
    do {
        while (($code = curl_multi_exec($queue, $active)) == CURLM_CALL_MULTI_PERFORM) ;

        if ($code != CURLM_OK) {
            break;
        }

        // a request was just completed -- find out which one
        while ($done = curl_multi_info_read($queue)) {

            // get the info and content returned on the request
            $info = curl_getinfo($done['handle']);
            $error = curl_error($done['handle']);
            $results = callback(curl_multi_getcontent($done['handle']), $delay, $map[(string)$done['handle']]);
            $responses[$map[(string)$done['handle']]] = compact('info', 'error', 'results');

            // remove the curl handle that just completed
            curl_multi_remove_handle($queue, $done['handle']);
            curl_close($done['handle']);
        }

        // Block for data in / output; error handling is done by curl_multi_exec
        if ($active > 0) {
            curl_multi_select($queue, 0.5);
        }

    } while ($active);

    curl_multi_close($queue);
    return $responses;
}

function callback($data, $delay, $url)
{
    //echo $data;
    //echo date("Y-m-d H:i:s", time()) . " --- " . $url . "\n";
    if (!empty($data)) {
        file_put_contents("./html2/" . md5($url) . ".html", $data);
    }
    // usleep模拟现实中比较负责的数据处理逻辑(如提取, 分词, 写入文件或数据库等)
    //usleep(1);
    //return compact('data', 'matches');
}

