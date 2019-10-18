<?php

namespace NonePHP\Component\Cache;

abstract class Format implements FormatInterface
{
    protected $_lifetime;

    public function __construct(array $options = [])
    {
        if (!empty($options['lifetime'])) {
            $this->_lifetime = $options['lifetime'];
        } else {
            $this->_lifetime = 86400;
        }
    }

    public function setLifetime(int $lifetime): bool
    {
        $this->_lifetime = $lifetime;
        return true;
    }

    public function getLifetime(): int
    {
        return $this->_lifetime;
    }
}