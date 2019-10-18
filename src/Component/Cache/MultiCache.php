<?php

/**
 * 多级缓存
 */

namespace NonePHP\Component\Cache;

trait MultiCache
{
    private $_nextHandler;

    public function setNext(CacheInterface $cache): void
    {
        $this->_nextHandler = $cache;
    }

    public function hasNext(): bool
    {
        return $this->_nextHandler && $this->_nextHandler instanceof CacheInterface;
    }

    public function getNext(): CacheInterface
    {
        return $this->_nextHandler;
    }
}