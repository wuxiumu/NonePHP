<?php

namespace NonePHP\Tool;

use NonePHP\Component\Cache\Backend\Yac as YacCache;
use NonePHP\Component\Cache\Format\FormatNull;
use RuntimeException;
use function gettype;
use function is_array;
use function is_string;

class Yac
{
    protected $lifetime = 86400;
    protected $yac_client;

    public function __construct(array $params = [])
    {
        $connection = new YacCache(new FormatNull(), [
            'prefix' => $params['prefix'] ?? '_NonePHP_',
        ]);
        $this->yac_client = $connection->getYac();
        $this->lifetime = $params['lifetime'] ?? $this->lifetime;
    }

    public function set($key, $value = '')
    {
        if (is_string($key) && empty($value)) {
            throw new RuntimeException('缓存值不能为空');
        }

        if (is_string($key)) {
            return $this->yac_client->set($key, $value, $this->lifetime);
        }

        if (is_array($key)) {
            return $this->yac_client->set($key, $this->lifetime);
        }

        throw new RuntimeException('缓存key类型有误[' . gettype($key) . ']');
    }

    public function get($key)
    {
        if (!is_array($key) && !is_string($key)) {
            throw new RuntimeException('缓存查询key类型有误[' . gettype($key) . ']');
        }

        return $this->yac_client->get($key);
    }

    public function delete($key): bool
    {
        if (!is_array($key) && !is_string($key)) {
            throw new RuntimeException('缓存删除key类型有误[' . gettype($key) . ']');
        }

        return $this->yac_client->delete($key);
    }

    public function flush()
    {
        return $this->yac_client->flush();
    }

    public function info()
    {
        return $this->yac_client->info();
    }
}