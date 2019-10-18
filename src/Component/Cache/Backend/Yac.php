<?php

namespace NonePHP\Component\Cache\Backend;

use NonePHP\Component\Cache\Cache;
use NonePHP\Component\Cache\FormatInterface;
use NonePHP\Component\Cache\MultiCache;

class Yac extends Cache
{
    use MultiCache;

    protected $yac_client;

    public function __construct(FormatInterface $format = null, array $options = [])
    {
        $this->yac_client = new \Yac($options['prefix'] ?? '');
        parent::__construct($format, $options);
    }

    public function get(string $name)
    {
        $value = $this->yac_client->get($name);
        if ($value && $this->_format) {
            $value = $this->_format->afterRetrieve($value);
        }

        if (!$value && $this->hasNext()) {
            return $this->getNext()->get($name);
        }
        return $value;
    }

    public function save(string $name, $value, $lifetime = null): bool
    {
        $_value = $value;
        if ($_value && $this->_format) {
            $_value = $this->_format->beforeSave($value);
        }
        $_lifetime = $lifetime ?: $this->_format->getLifetime();
        $ret = $this->yac_client->set($name, $_value, $_lifetime);
        if (!$this->hasNext()) {
            return $ret;
        }

        return $this->getNext()->save($name, $value, $lifetime);
    }

    public function delete(string $name): bool
    {
        $ret = $this->exists($name) && $this->yac_client->delete($name);

        if (!$this->hasNext()) {
            return $ret;
        }
        return $this->getNext()->delete($name);
    }

    public function exists(string $name): bool
    {
        return !empty($this->yac_client->get($name)) || ($this->hasNext() && $this->getNext()->exists($name));
    }

    // ---- YAC ----
    public function getYac(): \Yac
    {
        return $this->yac_client;
    }
}