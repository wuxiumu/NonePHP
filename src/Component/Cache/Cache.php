<?php

namespace NonePHP\Component\Cache;

abstract class Cache implements CacheInterface
{
    protected $_format;
    protected $_prefix = '';

    public function __construct(FormatInterface $format = null, array $options = [])
    {
        $this->_format = $format;
        if (!empty($options['prefix'])) {
            $this->_prefix = $options['prefix'];
        }
    }

    public function getFormat() :FormatInterface
    {
        return $this->_format;
    }

    public function getPrefix()
    {
        return $this->_prefix;
    }
}