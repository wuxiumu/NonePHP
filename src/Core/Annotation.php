<?php

/**
 * Application 使用此类获取接口的 middleware 注解
 * 如果大量的通过注解，需要手动设置 annotations 增加缓存服务来提升新能
 */

namespace NonePHP\Core;

use NonePHP\BaseException;
use NonePHP\Component\Cache\CacheInterface;
use NonePHP\Exception\UsageErrorException;
use ReflectionException;
use ReflectionMethod;

class Annotation
{
    protected $annotations = [];

    protected $_cache;

    public function __construct(CacheInterface $_cache = null)
    {
        $this->_cache = $_cache;
    }

    public function getCache(): CacheInterface
    {
        return $this->_cache;
    }

    /**
     * 查询 method 的备注
     * @param string $class
     * @param string $method
     * @return array|mixed|null
     * @throws BaseException
     */
    public function getMethod(string $class, string $method)
    {
        $key = md5($class . $method);
        if (!empty($this->annotations[$key])) {
            return $this->annotations[$key];
        }

        $annotations = null;
        if ($this->_cache) {
            $annotations = $this->_cache->get('AT:' . $key);
        }

        if ($annotations) {
            $this->annotations[$key] = $annotations;
            return $annotations;
        }

        // parse
        $annotations = $this->parse($class, $method);
        $this->annotations[$key] = $annotations;
        if ($this->_cache) {
            $this->_cache->save('AT:' . $key, $annotations);
        }

        return $annotations;
    }

    protected function parse(string $class, $method): ?array
    {
        if (!class_exists($class)) {
            throw (new UsageErrorException())->debug('parse annotations error: class not exists, ' . $class);
        }

        try {
            $refClass = new ReflectionMethod($class, $method);
            $comment = $refClass->getDocComment();
            $comments = explode("\n", $comment);

            $annotations = [];
            array_map(static function ($value) use (&$annotations) {
                $_value = trim($value);
                if (strpos($_value, '*') === 0) {
                    $_value = trim(ltrim($_value, '*'));
                    if (!$_value || $_value === '/') {
                        return;
                    }

                    $_value = explode(' ', $_value);
                    if (count($_value) >= 2) {
                        $annotations[] = $_value;
                    }
                }
            }, $comments);

            return $annotations;
        } catch (ReflectionException $e) {
            throw (new UsageErrorException())->debug('parse annotations error: ' . $e->getMessage());
        }
    }
}