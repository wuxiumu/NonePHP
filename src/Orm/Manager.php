<?php

namespace NonePHP\Orm;

use NonePHP\DI;
use NonePHP\Exception\SystemConfigException;

trait Manager
{
    /**
     * 通用连接，如果读写一致，使用此连接
     * @var array
     */
    protected static $_commonConnections = [];

    /**
     * 写连接
     * @var array
     */
    protected static $_writeConnections = [];

    /**
     * 读连接
     * @var array
     */
    protected static $_readConnections = [];

    /**
     * Query 对象
     * @var $_query
     */
    protected static $_query;

    /**
     * @return Query
     */
    public static function _getQuery(): Query
    {
        if (!static::$_query) {
            static::$_query = new Query(static::class);
        }

        return static::$_query;
    }

    protected function writeSameWithRead()
    {
        $_common = $this->getConnection();
        $_write = $this->getWriteConnection();
        $_read = $this->getReadConnection();
        if (!$_write && !$_read) {
            return $_common;
        }

        if (!$_write && $_common === $_read) {
            return $_common;
        }

        if (!$_read && $_common === $_write) {
            return $_common;
        }

        if ($_write === $_read) {
            return $_write;
        }

        if ($_common === $_write && $_common === $_read) {
            return $_common;
        }

        return false;
    }

    public function _getDbConnection(string $type): Pdo
    {
        if (!in_array($type, ['write', 'read'])) {
            $type = 'write';
        }

        $key = md5(static::class);
        $dbService = $this->writeSameWithRead();
        if (!$dbService) {
            if ($type === 'write') {
                if (in_array($key, static::$_writeConnections, true)) {
                    return static::$_writeConnections[$key];
                }
                $dbService = $this->getWriteConnection();
            }

            if ($type === 'read') {
                if (in_array($key, static::$_readConnections, true)) {
                    return static::$_readConnections[$key];
                }
                $dbService = $this->getReadConnection();
            }
            if (!$dbService) {
                throw (new SystemConfigException())->debug('db service name must not be empty');
            }
        } else if (in_array($key, static::$_commonConnections, true)) {
            return static::$_commonConnections[$key];
        }

        if (!DI::getInstance()->has($dbService)) {
            throw (new SystemConfigException())->debug('cannot found db service: ' . $dbService);
        }

        $dbService = DI::getInstance()->getShared($dbService);

        if (!$dbService instanceof Pdo) {
            throw (new SystemConfigException())->debug('only support \'NonePHP\Orm\PDO\' for db service');
        }

        if ($this->writeSameWithRead()) {
            static::$_commonConnections[$key] = $dbService;
        } else if ($type === 'write') {
            static::$_writeConnections[$key] = $dbService;
        } else {
            static::$_readConnections[$key] = $dbService;
        }

        return $dbService;
    }
}