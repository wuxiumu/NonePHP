<?php

namespace NonePHP\Tool;

use NonePHP\Component\Cache\Format\FormatNull;
use NonePHP\SingleInstance;

class Redis
{
    use SingleInstance;
    public $cache;

    public function __construct(array $params = [])
    {
        $format = new FormatNull([
            'lifetime' => 86400,
        ]);
        $options = [
            'host' => $params['host'] ?? env('REDIS_HOST'),
            'port' => $params['port'] ?? env('REDIS_PORT'),
            'prefix' => $params['prefix'] ?? env('REDIS_PREFIX'),
            'database' => $params['database'] ?? env('REDIS_DATABASE')
        ];
        if ($pwd = ($params['password'] ?? env('REDIS_PASSWORD'))) {
            $options['password'] = $pwd;
        }
        $this->cache = new \NonePHP\Component\Cache\Backend\Redis($format, $options);
    }

    /**
     * 常用的四个，name 会自动加上 prefix
     * 也可以使用
     *  $redis = $this->getRedis();
     *  $redis->set($this->getPrefixName($name), 'value')
     * 组合调用原生方法
     * @param string $name
     * @return bool
     */
    public function exists(string $name): bool
    {
        return $this->cache->exists($name);
    }

    /**
     * 因为底层cache实现的是save方法，改成set方法，与Redis实现保持一致
     * @param string $name
     * @param $value
     * @param null $lifetime
     * @return bool
     */
    public function set(string $name, $value, $lifetime = null): bool
    {
        return $this->cache->save($name, $value, $lifetime);
    }

    public function get(string $name)
    {
        return $this->cache->get($name);
    }

    public function del(string $name): bool
    {
        return $this->cache->delete($name);
    }

    // 封装一些 Redis 常用的实现

    /**
     * Redis 实现单节点'分布式'锁
     *
     * usage:
     *
     *  $lockName = 'order:pay:201905120001';
     *  $lockValue = $this->lock($lockName); // 新加订单支付锁
     *  if (!$lockValue) {
     *      throw new Exception('请勿重复发起支付');
     *  }
     *  // do pay code ...
     *  $this->unLock($lockName, $lockValue); // 支付完成或者失败，都要主动释放锁，不要依赖过期释放，效率太低
     *
     * @param string $lockName
     * @param int $lockTime
     * @return bool|string 返回false自行决定如何后续处理，是抛出异常还是重试
     */
    public function lock(string $lockName, int $lockTime = 3)
    {
        $value = getmypid() . time();
        // 加锁失败的解决方法主要有, 1. 抛出指定异常，供前端决定重试；2. 重试，容易导致响应加长
        // 这里不去重试，只返回 false 供上层自己决定
        return $this->set('lock:' . $lockName, $value, ['EX' => $lockTime, 'NX']) ? $value : false;
    }

    /**
     * 任务结束释放锁
     * @param string $lockName
     * @param string $lockValue
     * @return bool
     */
    public function unLock(string $lockName, string $lockValue): bool
    {
        // 判断锁内容是否一致，所属权判断
        // if (!($_value = $this->get('lock:' . $lockName)) || $_value !== $lockValue) {
        // 所属权判断失败 返回false，上层自行决定如何处理
        // return false;
        // }
        // 中间间隙，如果锁失效，同时被其他进程获取锁，如何防止误删？ LUA 脚本
        // return $this->del('lock:' . $lockName);

        $luaScript = "if redis.call('get',KEYS[1]) == ARGV[1] then return redis.call('del',KEYS[1]) else return 0 end";
        return $this->getRedis()->evaluate($luaScript, [
            $this->getPrefixName('lock:' . $lockName),
            $lockValue
        ], 1);
    }


    /**
     * 自己加 prefix
     * @param $name
     * @return mixed|string
     */
    public function getPrefixName($name)
    {
        return $this->cache->getPrefix() . $name;
    }

    /**
     * 返回原生的 redis，使用的时候注意，不会自动加上 prefix
     * @return \Redis
     */
    public function getRedis(): \Redis
    {
        return $this->cache->getRedis();
    }
}
