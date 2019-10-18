<?php

namespace NonePHP;

trait SingleInstance
{
    protected static $_singletonStack = [];

    /**
     * @param mixed $params
     * @return static
     */
    public static function getInstance($params = null)
    {
        $class = static::class;
        $key = md5($class . serialize($params));
        if (!empty(static::$_singletonStack[$key])) {
            return static::$_singletonStack[$key];
        }

        if ($params) {
            static::$_singletonStack[$key] = new $class($params);
        } else {
            static::$_singletonStack[$key] = new $class();
        }
        return static::$_singletonStack[$key];
    }
}