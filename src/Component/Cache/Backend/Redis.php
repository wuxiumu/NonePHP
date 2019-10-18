<?php

namespace NonePHP\Component\Cache\Backend;

use NonePHP\Component\Cache\Cache;
use NonePHP\Component\Cache\FormatInterface;
use NonePHP\Component\Cache\MultiCache;
use NonePHP\Exception\SystemConfigException;
use Throwable;

class Redis extends Cache
{
    use MultiCache;

    protected $connection;

    public function __construct(FormatInterface $format = null, array $options = [])
    {
        $this->connection = new \Redis();
        try {
            if ($options['persistent']) {
                $ret = $this->connection->pconnect(
                    $options['host'],
                    $options['port'],
                    $options['timeout']
                );
            } else {
                $ret = $this->connection->connect(
                    $options['host'],
                    $options['port'],
                    $options['timeout']
                );
            }
        } catch (Throwable $e) {
            throw (new SystemConfigException('redis 连接出错'))->debug($e->getMessage());
        }
        if (!$ret) {
            throw new SystemConfigException('redis 连接错误');
        }
        if ($password = $options['password'] and !$this->connection->auth($password)) {
            throw new SystemConfigException('redis 认证错误');
        }
        if ($db = $options['database'] and !$this->connection->select((int)$db)) {
            throw new SystemConfigException('redis 选择db错误');
        }
        if (empty($options['prefix'])) {
            $options['prefix'] = 'NonePHP:';
        } else {
            $options['prefix'] = trim($options['prefix'], ':') . ':';
        }
        parent::__construct($format, $options);
    }

    /**
     * @param string $name
     * @param $value
     * @param null $lifetime
     * @param array $options Redis 特有参数
     * @return bool
     */
    public function save(string $name, $value, $lifetime = null, array $options = []): bool
    {
        $_value = $value;
        if ($value && $this->_format) {
            $_value = $this->_format->beforeSave($value);
        }
        $_lifetime = $lifetime ?: $this->_format->getLifetime();
        if (!array_key_exists('EX', $options)) {
            $options['EX'] = $_lifetime;
        }
        $ret = $this->connection->set($this->_getPrefixName($name), $_value, $options);
        if (!$this->hasNext()) {
            return $ret;
        }
        return $this->getNext()->save($name, $value, $lifetime);
    }

    public function exists(string $name): bool
    {
        return $this->connection->exists($this->_getPrefixName($name)) || ($this->hasNext() && $this->getNext()->exists($name));
    }

    public function get(string $name)
    {
        if (!$this->exists($name)) {
            return false;
        }
        $value = $this->connection->get($this->_getPrefixName($name));
        if ($value && $this->_format) {
            $value = $this->_format->afterRetrieve($value);
        }

        if (!$value && $this->hasNext()) {
            return $this->getNext()->get($name);
        }
        return $value;
    }

    public function delete(string $name): bool
    {
        $ret = $this->exists($name) && $this->connection->del($this->_getPrefixName($name));
        if (!$this->hasNext()) {
            return $ret;
        }

        return $this->getNext()->delete($name);
    }

    // ---- REDIS ----

    public function getRedis(): \Redis
    {
        return $this->connection;
    }

    protected function _getPrefixName(string $name): string
    {
        return $this->_prefix . $name;
    }
}