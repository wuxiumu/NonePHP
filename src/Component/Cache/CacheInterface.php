<?php

namespace NonePHP\Component\Cache;

interface CacheInterface
{
    public function get(string $name);
    public function save(string $name, $value, $lifetime = null) :bool;
    public function delete(string $name) :bool;
    public function exists(string $name) :bool;
}