<?php

namespace NonePHP\Component\Cache\Backend;

use NonePHP\Component\Cache\Cache;
use NonePHP\Component\Cache\FormatInterface;

class File extends Cache
{
    protected $_cache_dir;
    protected $_lastLifetime;

    /**
     * File constructor.
     * @param FormatInterface $format
     * @param array $options
     */
    public function __construct(FormatInterface $format, array $options = [])
    {
        parent::__construct($format, $options);
        if (!empty($options['dir'])) {
            $this->_cache_dir = $options['dir'];
        } else {
            $this->_cache_dir = STORAGE_DIR . DIRECTORY_SEPARATOR . 'cache';
        }
        if (!$this->_prefix) {
            $this->_prefix = '_cache_file_';
        }
    }

    public function get(string $name)
    {
        if (!$this->isFile($name)) {
            return false;
        }
        $cache_file = $this->getCacheFile($name);
        $value = file_get_contents($cache_file);
        $value = explode('|', $value, 3);
        $lastUpdate = filemtime($cache_file) ?? 0;
        if (count($value) < 3 || time() > $lastUpdate + $value[1]) {
            unlink($cache_file);
            return null;
        }

        $value = $value[2] ?? '';
        if (is_numeric($value)) {
            return $value;
        }

        return $this->_format->afterRetrieve($value);
    }

    public function save(string $name, $value, $lifetime = null): bool
    {
        if (!is_numeric($value)) {
            $value = $this->_format->beforeSave($value);
        }
        $value = date('Y-m-d H:i:s') . '|' . ($lifetime ?: $this->_format->getLifetime()) . '|' . $value;

        return (bool)file_put_contents($this->getCacheFile($name), $value);
    }

    public function delete(string $name): bool
    {
        return unlink($this->getCacheFile($name));
    }

    public function exists(string $name): bool
    {
        return (bool)$this->get($name);
    }

    protected function isFile(string $name): bool
    {
        return is_file($this->getCacheFile($name));
    }

    protected function getCacheFile($name): string
    {
        return rtrim($this->_cache_dir . '/') . '/' . $this->_prefix . $name;
    }
}