<?php
namespace Maple\Caches;

use Maple\Utils\Log;

/**
 * @package
 *
 * @version 2.7.0
 * @copyright 1997-2015 The PHP Group
 * @author seatle <seatle@foxmail.com>
 * @created time :2015-12-13
 */
class RedisHelper
{
    /**
     *  redis链接标识符号
     * @var \Redis
     */
    private $redis = NULL;

    /**
     *  redis配置数组
     */
    private $configs = [];

    /**
     *  默认redis前缀
     */
    const PREFIX = "Maple";

    private $error = "";

    /**
     * 测试redis能否正常连接
     * @return bool
     */
    public static function check()
    {
        // 获取配置
        if (empty($GLOBALS['config']['redis'])) {
//            $this->error = "You not set a config array for connect";
            return false; // TODO
        }

        $configs = $GLOBALS['config']['redis'];

        if (!extension_loaded("redis")) {
//            $this->error = "Unable to load redis extension";
            return false; //todo
        }

        $redis = new \Redis();
        // 注意长连接在多进程环境下有问题，反正都是CLI环境，没必要用长连接
        if (!$redis->connect($configs['host'], $configs['port'], $configs['timeout'])) {
//            $this->error = "Unable to connect to redis server";
            return false;
        }

        // 验证
        if ($configs['pass']) {
            if (!$redis->auth($configs['pass'])) {
//                $this->error = "Redis Server authentication failed";
                return false;
            }
        }
        $redis->close();
        $redis = null;
        return true;
    }

    private function close()
    {
        $this->redis->close();
        $this->redis = null;
    }

    /**
     * @throws \Exception
     */
    public function init()
    {
        // 获取配置
        $this->configs = self::getDefaultConfig();
        if (empty($this->configs)) {
            $this->error = "You not set a config array for connect";
            Log::error($this->error);
            exit();
        }

        // 如果当前链接标识符为空，或者ping不同，就close之后重新打开
        if (empty($this->redis) || !self::ping()) {
            // 如果当前已经有链接标识符，但是ping不了，则先关闭
            if (!empty($this->redis)) {
                $this->redis->close();
            }

            $this->redis = new \Redis();
            $this->connect();
        }

    }

    private function connect()
    {
        try {
            $res = $this->redis->connect($this->configs['host'], $this->configs['port'], $this->configs['timeout']);
        } catch (\Error $e) {
            $this->error = $e->getMessage();
            $this->redis = null;
            throw new \Exception('Unable to connect to redis server');
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $this->redis = null;
            throw new \Exception('Unable to connect to redis server');
        }
        if (!$res) {
            $this->redis = null;
            throw new \Exception('Unable to connect to redis server');
        }
        // 验证
        if ($this->configs['pass']) {
            $this->auth();
        }
        $prefix = $this->configs['prefix'] ?? self::PREFIX;
        $this->redis->setOption(\Redis::OPT_PREFIX, $prefix . ":");
    }

    private function auth()
    {
        if (!$this->redis->auth($this->configs['pass'])) {
            $this->error = "Redis Server authentication failed";
            $this->redis = null;
            throw new \Exception('Redis Server authentication failed');
        }
    }

//    public static function setConnect(array $config = [])
//    {
//        // 先断开原来的连接
//        if (!empty($this->redis)) {
//            $this->redis->close();
//            $this->redis = null;
//        }
//
//        if (!empty($config)) {
//            $this->configs = $config;
//        } else {
//            if (empty($this->configs)) {
//                throw new \Exception("You not set a config array for connect!");
//            }
//        }
//    }
//
//    public static function setConnectDefault(array $config = [])
//    {
//        if (empty($config)) {
//            $config = self::getDefaultConfig();
//        }
//        self::setConnect($config);
//    }

    /**
     * 加载配置
     * @return array
     */
    protected static function getDefaultConfig(): array
    {
        $configs = $GLOBALS['config']['redis'] ?? [];
        return $configs;
    }

    /**
     * set new key
     * @param $key
     * @param $value
     * @param int $expire
     * @return bool
     */
    public function set(string $key, string $value, int $expire = 0): bool
    {
        if ($expire > 0) {
            return $this->redis->setex($key, $expire, $value);
        } else {
            return $this->redis->set($key, $value);
        }
    }

    /**
     *Set the string value in argument as value of the key if the key doesn't already exist in the database.
     *
     * @param mixed $key 键
     * @param mixed $value 值
     * @param int $expire 过期时间，单位：秒
     * @return bool
     * @author seatle <seatle@foxmail.com>
     * @created time :2015-12-13 01:05
     */
    public function setNx(string $key, string $value, int $expire = 0): bool
    {
        if ($expire > 0) {
            return $this->redis->setex($key, $expire, $value); // todo
        } else {
            return $this->redis->setnx($key, $value);
        }
    }

    /**
     * 获取键对应的值
     * @param $key
     * @return bool|null|string
     */
    public function get(string $key)
    {
        return $this->redis->get($key);
    }

    /**
     * del 删除数据
     * @param string $key
     */
    public function del(string $key)
    {
        $this->redis->del($key);
    }

    /**
     * type 返回值的类型
     * @param string $key
     * @return mixed
     */
    public function type(string $key)
    {
        $types = [
            '0' => 'set',
            '1' => 'string',
            '3' => 'list'
        ];
        return $types[$this->redis->type($key)];
    }

    /**
     * incr 名称为key的string增加integer, integer为0则增1
     * @param $key
     * @param int $integer
     * @return int 新的数量
     */
    public function incr($key, $integer = 0): int
    {
        if ($integer === 0) {
            return $this->redis->incr($key);
        } else {
            return $this->redis->incrby($key, $integer);
        }
    }

    /**
     * decr 名称为key的string减少integer, integer为0则减1
     * @param $key
     * @param int $integer
     * @return int
     */
    public function decr(string $key, int $integer = 0): int
    {
        if (empty($integer)) {
            return $this->redis->decr($key);
        } else {
            return $this->redis->decrby($key, $integer);
        }
    }

    /**
     * append 名称为key的string的值附加value
     * @param string $key
     * @param $value
     * @return int
     */
    public function append(string $key, string $value): int
    {
        return $this->redis->append($key, $value);
    }

    /**
     * substr 返回名称为key的string的value的子串
     * @param string $key
     * @param int $start
     * @param int $end
     * @return string
     */
    public function substr(string $key, int $start, int $end)
    {
        return $this->redis->getRange($key, $start, $end);
    }

    /**
     * select database
     * @param int $index
     */
    public function select(int $index)
    {
        $this->redis->select($index);
    }

    /**
     * dbsize 返回当前数据库中key的数目
     * @return int
     */
    public function dbsize(): int
    {
        return $this->redis->dbsize();
    }

    /**
     * flushdb 删除当前选择数据库中的所有key
     * @return bool
     */
    public function flushdb(): bool
    {
        return $this->redis->flushdb();
    }

    /**
     * flushall 删除所有数据库中的所有key
     * @return bool
     */
    public function flushall(): bool
    {
        return $this->redis->flushall();
    }

    /**
     * save 将数据保存到磁盘
     * @param bool $isAsySave
     * @return bool
     */
    public function save($isAsySave = false)
    {
        if (!$isAsySave) {
            return $this->redis->save();
        } else {
            return $this->redis->bgsave();
        }
    }

    /**
     * info 提供服务器的信息和统计
     * @return string
     */
    public function info(): string
    {
        return $this->redis->info();
    }

    /**
     * slowlog 慢查询日志
     * @param string $command
     * @param int $len
     * @return mixed|null
     */
    public function slowLog(string $command = 'get', int $len = 0)
    {
        if (!empty($len)) {
//                return $this->redis->slowlog($command, $len); //TODO
        } else {
            return $this->redis->slowlog($command);
        }
    }

    /**
     * lastsave 返回上次成功将数据保存到磁盘的Unix时戳
     * @return int
     */
    public function lastSave(): int
    {
        return $this->redis->lastsave();
    }

    /**
     * left push 将数据从左边压入
     * @param string $key
     * @param string $value
     * @return int
     */
    public function lPush(string $key, string $value)
    {
        return $this->redis->lPush($key, $value);
    }

    /**
     * right push 将数据从右边压入
     * @param $key
     * @param $value
     * @return int|null
     */
    public function rPush($key, $value)
    {
        return $this->redis->rpush($key, $value);
    }

    /**
     * left pop 从左边弹出数据, 并删除数据
     * @param $key
     * @return string
     */
    public function lPop(string $key)
    {
        return $this->redis->lpop($key);
    }

    /**
     * right pop 从右边弹出数据, 并删除数据
     * @param $key
     * @return string
     */
    public function rPop(string $key)
    {
        return $this->redis->rpop($key);
    }

    /**
     * left size 队列长度，同left len
     * @param string $key
     * @return int
     */
    public function lSize(string $key)
    {
        return $this->redis->lLen($key);
    }

    /**
     * left get 获取数据
     * @param string $key
     * @param int $index
     * @return string
     */
    public function lIndex(string $key, int $index = 0)
    {
        return $this->redis->lIndex($key, $index);
    }

    /**
     * lRange 获取范围数据
     * @param string $key
     * @param int $start
     * @param int $end
     * @return array
     */
    public function lRange(string $key, int $start, int $end): array
    {
        return $this->redis->lRange($key, $start, $end);
    }

    /**
     * right list 从右边弹出 $length 长度数据，并删除数据
     * @param string $key
     * @param int $length
     * @return array
     */
    public function rList(string $key, int $length): array
    {
        $queue_length = $this->lSize($key);
        // 如果队列中有数据
        if ($queue_length > 0) {
            $list = [];
            $count = ($queue_length >= $length) ? $length : $queue_length;
            for ($i = 0; $i < $count; $i++) {
                $data = $this->rPop($key);
                if ($data === false) {
                    continue;
                }

                $list[] = $data;
            }
            return $list;
        } else {
            // 没有数据返回NULL
            return [];
        }
    }

    /**
     * 查找符合给定模式的key。
     * KEYS *命中数据库中所有key。
     * KEYS h?llo命中hello， hallo and hxllo等。
     * KEYS h*llo命中hllo和heeeeello等。
     * KEYS h[ae]llo命中hello和hallo，但不命中hillo。
     * 特殊符号用"\"隔开
     * 因为这个类加了OPT_PREFIX前缀，所以并不能真的列出redis所有的key，需要的话，要把前缀去掉
     * @param string $key
     * @return array
     */
    public function keysArray(string $key)
    {
        return $this->redis->keys($key);
    }

    /**
     * ttl 返回某个KEY的过期时间
     * 正数：剩余多少秒
     * -1：永不超时
     * -2：key不存在
     * @param string $key
     * @return int
     */
    public function ttl(string $key)
    {
        return $this->redis->ttl($key);
    }

    /**
     * expire 为某个key设置过期时间,同setTimeout
     *
     * @param string $key
     * @param mixed $expire
     * @return bool
     * @author seatle <seatle@foxmail.com>
     * @created time :2015-12-13 01:05
     */
    public function expire(string $key, $expire)
    {
        return $this->redis->expire($key, $expire);
    }

    /**
     *key值是否存在
     * @param $key
     * @return bool
     */
    public function exists(string $key)
    {
        return $this->redis->exists($key);
    }

    /**
     * ping 检查当前redis是否存在且是否可以连接上
     *
     * @return bool
     * @author seatle <seatle@foxmail.com>
     * @created time :2015-12-13 01:05
     */
    protected function ping()
    {
        if (empty ($this->redis)) {
            return false;
        }
        return $this->redis->ping() == '+PONG';
    }

    public static function encode($value)
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    public static function decode($value)
    {
        return json_decode($value, true);
    }
}
