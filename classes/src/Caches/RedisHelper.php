<?php
namespace Maple\Caches;
//todo 重写
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
    protected static $redis = NULL;

    /**
     *  redis配置数组
     */
    protected static $configs = [];

    /**
     *  默认redis前缀
     */
    public static $prefix = "Maple";

    public static $error = "";

    /**
     * 测试redis能否正常连接
     * @return bool
     */
    public static function check()
    {
        // 获取配置
        if (empty($GLOBALS['config']['redis'])) {
            self::$error = "You not set a config array for connect";
            return false; // TODO
        }

        $configs = $GLOBALS['config']['redis'];

        if (!extension_loaded("redis")) {
            self::$error = "Unable to load redis extension";
            return false; //todo
        }

        $redis = new \Redis();
        // 注意长连接在多进程环境下有问题，反正都是CLI环境，没必要用长连接
        if (!$redis->connect($configs['host'], $configs['port'], $configs['timeout'])) {
            self::$error = "Unable to connect to redis server";
            return false;
        }

        // 验证
        if ($configs['pass']) {
            if (!$redis->auth($configs['pass'])) {
                self::$error = "Redis Server authentication failed";
                return false;
            }
        }
        $redis->close();
        $redis = null;
        return true;
    }

    public static function close()
    {
        self::$redis->close();
        self::$redis = null;
    }

    /**
     * @return \Redis
     * @throws \Exception
     */
    public static function init(): \Redis
    {
        // 获取配置
        $configs = self::$configs ?? self::getDefaultConfig();
        if (empty($configs)) {
            self::$error = "You not set a config array for connect";
            throw new \Exception('You not set a config array for connect');
        }

        // 如果当前链接标识符为空，或者ping不同，就close之后重新打开
        if (empty(self::$redis) || !self::ping()) {
            // 如果当前已经有链接标识符，但是ping不了，则先关闭
            if (!empty(self::$redis)) {
                self::$redis->close();
            }

            self::$redis = new \Redis();
            if (!self::$redis->connect($configs['host'], $configs['port'], $configs['timeout'])) {
                self::$error = "Unable to connect to redis server";
                self::$redis = null;
                throw new \Exception('Unable to connect to redis server');
            }

            // 验证
            if ($configs['pass']) {
                if (!self::$redis->auth($configs['pass'])) {
                    self::$error = "Redis Server authentication failed";
                    self::$redis = null;
                    throw new \Exception('Redis Server authentication failed');
                }
            }

            $prefix = empty($configs['prefix']) ? self::$prefix : $configs['prefix'];
            self::$redis->setOption(\Redis::OPT_PREFIX, $prefix . ":");
        }

        return self::$redis;
    }

    public static function setConnect(array $config = [])
    {
        // 先断开原来的连接
        if (!empty(self::$redis)) {
            self::$redis->close();
            self::$redis = null;
        }

        if (!empty($config)) {
            self::$configs = $config;
        } else {
            if (empty(self::$configs)) {
                throw new \Exception("You not set a config array for connect!");
            }
        }
    }

    public static function setConnectDefault(array $config = [])
    {
        if (empty($config)) {
            $config = self::getDefaultConfig();
        }
        self::setConnect($config);
    }

    /**
     * 获取默认配置
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
    public static function set(string $key, string $value, int $expire = 0): bool
    {
        $redis = self::init();

        if ($redis) {
            if ($expire > 0) {
                return $redis->setex($key, $expire, $value);
            } else {
                return $redis->set($key, $value);
            }
        }

        return false;
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
    public static function setNx(string $key, string $value, int $expire = 0): bool
    {
        $redis = self::init();

        if ($redis) {
            if ($expire > 0) {
                return $redis->setex($key, $expire, $value); // todo
            } else {
                return $redis->setnx($key, $value);
            }
        }

        return false;
    }

    /**
     * 获取键对应的值
     * @param $key
     * @return bool|null|string
     */
    public static function get(string $key)
    {
        $redis = self::init();
        if ($redis) {
            return $redis->get($key);
        }
        return NULL;
    }

    /**
     * del 删除数据
     * @param string $key
     */
    public static function del(string $key)
    {
        $redis = self::init();
        if ($redis) {
            $redis->del($key);
        }
    }

    /**
     * type 返回值的类型
     * @param string $key
     * @return mixed
     */
    public static function type(string $key)
    {
        $redis = self::init();
        $types = [
            '0' => 'set',
            '1' => 'string',
            '3' => 'list'
        ];

        if ($redis) {
            return $types[$redis->type($key)];
        }
    }

    /**
     * incr 名称为key的string增加integer, integer为0则增1
     * @param $key
     * @param int $integer
     * @return int 新的数量
     */
    public static function incr($key, $integer = 0): int
    {
        $redis = self::init();

        if ($redis) {
            if ($integer === 0) {
                return $redis->incr($key);
            } else {
                return $redis->incrby($key, $integer);
            }
        }
        return 0;
    }

    /**
     * decr 名称为key的string减少integer, integer为0则减1
     * @param $key
     * @param int $integer
     * @return int
     */
    public static function decr(string $key, int $integer = 0): int
    {
        $redis = self::init();

        if ($redis) {
            if (empty($integer)) {
                return $redis->decr($key);
            } else {
                return $redis->decrby($key, $integer);
            }
        }
    }

    /**
     * append 名称为key的string的值附加value
     * @param string $key
     * @param $value
     * @return int
     */
    public static function append(string $key, string $value): int
    {
        $redis = self::init();

        if ($redis) {
            return $redis->append($key, $value);
        }
    }

    /**
     * substr 返回名称为key的string的value的子串
     * @param string $key
     * @param int $start
     * @param int $end
     * @return string
     */
    public static function substr(string $key, int $start, int $end)
    {
        $redis = self::init();

        if ($redis) {
            return $redis->getRange($key, $start, $end);
        }
    }

    /**
     * select database
     * @param int $index
     */
    public static function select(int $index)
    {
        $redis = self::init();

        if ($redis) {
            $redis->select($index);
        }
    }

    /**
     * dbsize 返回当前数据库中key的数目
     * @return int
     */
    public static function dbsize(): int
    {
        $redis = self::init();

        if ($redis) {
            return $redis->dbsize();
        }
    }

    /**
     * flushdb 删除当前选择数据库中的所有key
     * @return bool
     */
    public static function flushdb(): bool
    {
        $redis = self::init();

        if ($redis) {
            return $redis->flushdb();
        }
        return false;
    }

    /**
     * flushall 删除所有数据库中的所有key
     * @return bool
     */
    public static function flushall(): bool
    {
        $redis = self::init();
        if ($redis) {
            return $redis->flushall();
        }
        return false;
    }

    /**
     * save 将数据保存到磁盘
     * @param bool $isAsySave
     * @return bool
     */
    public static function save($isAsySave = false)
    {
        $redis = self::init();

        if ($redis) {
            if (!$isAsySave) {
                return $redis->save();
            } else {
                return $redis->bgsave();
            }
        }
        return false;
    }

    /**
     * info 提供服务器的信息和统计
     * @return string
     */
    public static function info(): string
    {
        $redis = self::init();
        if ($redis) {
            return $redis->info();
        }
    }

    /**
     * slowlog 慢查询日志
     * @param string $command
     * @param int $len
     * @return mixed|null
     */
    public static function slowLog(string $command = 'get', int $len = 0)
    {
        $redis = self::init();

        if ($redis) {
            if (!empty($len)) {
                return $redis->slowlog($command, $len); //TODO
            } else {
                return $redis->slowlog($command);
            }
        }

        return NULL;
    }

    /**
     * lastsave 返回上次成功将数据保存到磁盘的Unix时戳
     * @return int
     */
    public static function lastSave(): int
    {
        $redis = self::init();

        if ($redis) {
            return $redis->lastsave();
        }

        return NULL;
    }

    /**
     * left push 将数据从左边压入
     * @param string $key
     * @param string $value
     * @return int
     */
    public static function lPush(string $key, string $value)
    {
        $redis = self::init();

        if ($redis) {
            return $redis->lPush($key, $value);
        }

        return 0;
    }

    /**
     * right push 将数据从右边压入
     * @param $key
     * @param $value
     * @return int|null
     */
    public static function rPush($key, $value)
    {
        $redis = self::init();

        if ($redis) {
            return $redis->rpush($key, $value);
        }
        return 0;
    }

    /**
     * left pop 从左边弹出数据, 并删除数据
     * @param $key
     * @return string
     */
    public static function lPop(string $key)
    {
        $redis = self::init();
        if ($redis) {
            return $redis->lpop($key);
        }
        return '';
    }

    /**
     * right pop 从右边弹出数据, 并删除数据
     * @param $key
     * @return string
     */
    public static function rPop(string $key)
    {
        $redis = self::init();
        if ($redis) {
            return $redis->rpop($key);
        }
        return '';
    }

    /**
     * left size 队列长度，同left len
     * @param string $key
     * @return int
     */
    public static function lSize(string $key)
    {
        $redis = self::init();
        if ($redis) {
            return $redis->lLen($key);
        }
        return 0;
    }

    /**
     * left get 获取数据
     * @param string $key
     * @param int $index
     * @return string
     */
    public static function lIndex(string $key, int $index = 0)
    {
        $redis = self::init();
        if ($redis) {
            return $redis->lIndex($key, $index);
        }
        return NULL;
    }

    /**
     * lRange 获取范围数据
     * @param string $key
     * @param int $start
     * @param int $end
     * @return array
     */
    public static function lRange(string $key, int $start, int $end): array
    {
        $redis = self::init();
        if ($redis) {
            return $redis->lRange($key, $start, $end);
        }
        return [];
    }

    /**
     * right list 从右边弹出 $length 长度数据，并删除数据
     * @param string $key
     * @param int $length
     * @return array
     */
    public static function rList(string $key, int $length): array
    {
        $queue_length = self::lSize($key);
        // 如果队列中有数据
        if ($queue_length > 0) {
            $list = [];
            $count = ($queue_length >= $length) ? $length : $queue_length;
            for ($i = 0; $i < $count; $i++) {
                $data = self::rPop($key);
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
    public static function keysArray(string $key)
    {
        $redis = self::init();
        if ($redis) {
            return $redis->keys($key);
        }
        return [];
    }

    /**
     * ttl 返回某个KEY的过期时间
     * 正数：剩余多少秒
     * -1：永不超时
     * -2：key不存在
     * @param string $key
     * @return int
     */
    public static function ttl(string $key)
    {
        $redis = self::init();
        if ($redis) {
            return $redis->ttl($key);
        }
        return 0;
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
    public static function expire(string $key, $expire)
    {
        $redis = self::init();
        if ($redis) {
            return $redis->expire($key, $expire);
        }
        return false;
    }

    /**
     *key值是否存在
     * @param $key
     * @return bool
     */
    public static function exists(string $key)
    {
        $redis = self::init();
        if ($redis) {
            return $redis->exists($key);
        }
        return false;
    }

    /**
     * ping 检查当前redis是否存在且是否可以连接上
     *
     * @return bool
     * @author seatle <seatle@foxmail.com>
     * @created time :2015-12-13 01:05
     */
    protected static function ping()
    {
        if (empty (self::$redis)) {
            return false;
        }
        return self::$redis->ping() == '+PONG';
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


