<?php
/**
 * Description:借助curl完成抓取页面内容的功能，类似一个浏览器
 * User: Endless
 * Date: 2017/4/4
 * Time: 10:44
 */

namespace Maple\Helper;


class CurlHelper
{
    protected $userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_4) AppleWebKit/537.36 (KHTML, like Gecko) 
                                                    Chrome/44.0.2403.89 Safari/537.36';
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

    public function get(string $url, array $fields = [])
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