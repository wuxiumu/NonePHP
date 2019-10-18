<?php

namespace NonePHP\Orm;

use NonePHP\BaseException;
use NonePHP\DI;
use NonePHP\Exception\DatabaseException;
use RuntimeException;
use Throwable;
use function count;
use function in_array;
use function is_scalar;

abstract class Model
{
    use Manager;

    /**
     * 主键 默认是 id
     * @var string $pk
     */
    protected static $pk = 'id';

    protected static $_columns = [];

    /**
     * 缓存的数据，记录 update 之前的数据
     * @var array $_snapshot
     */
    protected static $_snapshot = [];

    /**
     * 支持的操作符
     * @var array
     */
    protected static $_support_op = [
        '=', // 默认的
        '!=',
        '<>',
        '>',
        '>=',
        '<',
        '<=',
        'like',
        'in'
    ];
    protected static $_support_op_mp = [
        '=' => 'equal',
        '!=' => 'notequal',
        '<>' => 'notequal',
        '>' => 'gt',
        '>=' => 'gtl',
        '<' => 'lt',
        '<=' => 'ltl',
        'like' => 'like',
        'in' => 'in',
    ];

    public function __construct(array $data = [])
    {
        static::_getQuery();
        $this->clear();
        $data && $this->assign($data);
    }

    protected static $_singletonStack = [];

    /**
     * @return static
     */
    protected static function _getInstance()
    {
        $class = static::class;
        $key = md5($class);
        if (!empty(static::$_singletonStack[$key])) {
            return static::$_singletonStack[$key];
        }

        static::$_singletonStack[$key] = new $class();
        return static::$_singletonStack[$key];
    }

    public static function _cloneResult(array $result): Model
    {
        /** @var Model $_model */
        $_model = clone static::_getInstance();
        foreach ($result as $key => $value) {
            $_model->$key = $value;
            if (!in_array($key, static::$_columns, true)) {
                static::$_columns[] = $key;
            }
        }

        static::$_snapshot = $result;
        return $_model;
    }

    /**
     * 子类需要使用此方法，明确指定数据库表名
     * @return mixed
     */
    abstract public function getTable();

    /**
     * 指定读写通用的连接服务名称
     * 返回 serviceName 需要通过 DI 设置数据库连接服务
     * $this->di->set('db', function () {
     *     return new Pdo(xx, xx, ...);
     * })
     * @return string
     */
    public function getConnection(): string
    {
        return 'db';
    }

    /**
     * 需要特殊指定的 读 & 写 连接服务；非空时，使用指定连接
     * @return string
     */
    public function getWriteConnection(): string
    {
        return '';
    }

    public function getReadConnection(): string
    {
        return '';
    }

    // 重写 beforeQuery & afterQuery 事件
//    public function beforeQuery(): void
//    {
//
//    }
//    public function afterQuery(): void
//    {
//
//    }

    // 设置默认的 pk 主键
    public function getPk(string $pk = ''): string
    {
        if ($pk && static::$pk !== $pk) {
            static::$pk = $pk;
        }
        return static::$pk;
    }

    public static function where(...$params): Model
    {
        $count = count($params);
        if ($count > 3 || $count < 2) {
            throw (new DatabaseException())->debug('where参数必须在2~3个之间');
        }

        foreach ($params as $param) {
            if (!is_scalar($param)) {
                throw (new DatabaseException())->debug('where参数必须为标量类型');
            }
        }

        $op1 = $params[0]; // 操作数 1
        $op2 = $count === 2 ? '=' : $params[1]; // 操作符
        $op3 = $count === 2 ? $params[1] : $params[2]; // 操作数 2

        if (!in_array($op2, static::$_support_op, true)) {
            throw new DatabaseException('不支持的操作符:' . $op2);
        }
        // in 查询特殊处理
        if ($op2 === 'in') {
            // 操作数 (1,2,3)
            $op3 = trim($op3, '()');
            $op3 = explode(',', $op3);
            $conditions = '`' . $op1 . '` ' . $op2 . ' (';
            $bindParams = [];
            foreach ($op3 as $i => $o) {
                $bindKey = ':' . $op1 . static::$_support_op_mp[$op2] . $i;
                $conditions .= $bindKey . ',';
                $bindParams[$bindKey] = $o;
            }
            $conditions = rtrim($conditions, ',') . ')';
        } else {
            $bindKey = ':' . $op1 . static::$_support_op_mp[$op2];
            $conditions = '`' . $op1 . '` ' . $op2 . ' ' . $bindKey;
            $bindParams = [
                $bindKey => $op3
            ];
        }

        static::_getInstance()::_getQuery()->where($conditions, $bindParams);

        return static::_getInstance();
    }

    // $fields eg: 'id,name,age'
    public static function fields(string $fields = ''): Model
    {
        if ($fields) {
            static::_getInstance()::_getQuery()->fields($fields);
        }
        return static::_getInstance();
    }

    public static function limit(int $limit, int $offset = 0): self
    {
        static::_getInstance()::_getQuery()->limit($limit, $offset);
        return static::_getInstance();
    }

    public static function order(string $field, string $order = 'asc'): self
    {
        $order = strtolower($order);
        if (!in_array($order, ['desc', 'asc'])) {
            $order = 'asc';
        }
        static::_getInstance()::_getQuery()->orderBy($field . ' ' . $order);
        return static::_getInstance();
    }

    public static function find()
    {
        $_table = static::_getInstance()->getTable();
        static::_getInstance()
            ::_getQuery()
            ->buildSelect($_table);


        return static::_getInstance()->execute();
    }

    /**
     * @return Model|bool
     * @throws BaseException
     */
    public static function findOne()
    {
        $_table = static::_getInstance()->getTable();
        static::_getInstance()
            ::_getQuery()
            ->limit(1)
            ->buildSelect($_table);

        $result = static::_getInstance()->execute();
        if (!$result) {
            return false;
        }

        $result->rewind();
        return $result->current();
    }

    public static function findByPk(int $pk)
    {
        return static::where(static::$pk, $pk)::findOne();
    }

    public function insert(array $data = [])
    {
        $data && $this->assign($data);
        $_table = $this->getTable();
        if (is_callable([$this, 'beforeSave'])) {
            $this->beforeSave();
        }

        $values = [];
        if (empty(static::$_columns)) {
            throw (new DatabaseException())->debug('insert fields cannot be empty');
        }
        foreach (static::$_columns as $column) {
            $values[$column] = $this->$column;
        }

        $this::_getQuery()
            ->buildInsert($_table, static::$_columns, $values);

        return $this->execute();
    }

    public function update(array $lockData = [], array $data = [])
    {
        $data && $this->assign($data);
        $_table = $this->getTable();
        if (is_callable([$this, 'beforeSave'])) {
            $this->beforeSave();
        }

        $changed = $this->getChange();
        if (!$changed) {
            throw (new DatabaseException())->debug('update but model has not changed');
        }

        $_pk = $this->getPk();
        $lockData = [$_pk => $this->$_pk] + $lockData;
        $lockData = array_unique($lockData);

        $this::_getQuery()
            ->buildUpdate($_table, $changed, $lockData);

        return $this->execute();
    }

    protected function execute()
    {
        $start = microtime(true);
        $_connection = null;
        $_query = static::_getQuery();
        if ($_query->getType() === 'select') {
            $_connection = $this->_getDbConnection('read');
        } else {
            $_connection = $this->_getDbConnection('write');
        }

        try {
            $prepared = $_connection->prepare($_query->getSQL());
            $ret = $_connection->executePrepared($prepared, $_query->getBindParams(), $_query->getBindTypes());
            if ($_query->getType() === 'select') {
                $result = new Result($_query, $ret, $this);
                if (!$result->count()) {
                    $result = false;
                }
            } else if ($_query->getType() === 'insert') {
                $result = (int)$_connection->_getPdo()->lastInsertId();
                if (!$result) {
                    $this->clear();
                    throw new RuntimeException(json_encode($_connection->_getPdo()->errorInfo(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                }
                $_pk = $this->getPk();
                $this->$_pk = $result;
            } else { // for update, delete
                $result = $ret->rowCount();
            }
        } catch (Throwable $e) {
            $debugException = [
                'msg' => $e->getMessage(),
                'sql' => $_query->getSQL(),
                'bind' => json_encode($_query->getBindParams())
            ];
            $this->clear();
            if (env('SQL_DEBUG', false) && DI::getInstance()->has('logger')) {
                static $SQL_CNT = 1;
                $_log = $SQL_CNT++ . ' | ' . sprintf('%.4f', (microtime(true) - $start) * 1000) . 'ms | ' .
                    $_query->getSQL() . ' | ' . json_encode($_query->getBindParams());
                XLog()->debug($_log, true);
            }

            throw (new DatabaseException())->debug($debugException);
        }

        if (env('SQL_DEBUG', false) && DI::getInstance()->has('logger')) {
            static $SQL_CNT = 1;
            $_log = $SQL_CNT++ . ' | ' . sprintf('%.4f', (microtime(true) - $start) * 1000) . 'ms | ' .
                $_query->getSQL() . ' | ' . json_encode($_query->getBindParams());
            XLog()->debug($_log, true);
        }

        $this->clear();
        return $result;
    }

    /**
     * 查询模型变更字段
     */
    public function getChange(): array
    {
        $changed = [];
        foreach ($this as $key => $value) {
            if ($value !== (static::$_snapshot[$key] ?? '') && $key !== $this->getPk()) {
                $changed[$key] = $value;
            }
        }
        return $changed;
    }

    protected function clear(): void
    {
        static::$_query = null;
        static::$_columns = [];
        static::$_snapshot = [];
    }

    protected function assign(array $data): void
    {
        foreach ($data as $key => $value) {
            if (is_scalar($value)) {
                static::$_columns[] = $key;
                $this->$key = $value;
            }
        }
    }

    // set get 魔术方法
    public function __set($name, $value)
    {
        $setter = 'set' . ucfirst($name);
        if (!in_array($name, static::$_columns, true)) {
            static::$_columns[] = $name;
        }
        if (method_exists($this, $setter)) {
            $this->$setter($value);
        } else {
            $this->$name = $value;
        }
    }

    public function __get($name)
    {
        $getter = 'get' . ucfirst($name);
        return method_exists($this, $getter) ? $this->$getter() : null;
    }

    public function __isset($name)
    {
        if (!($this->$name ?? false)) {
            $getter = 'get' . ucfirst($name);
            return method_exists($this, $getter) && $this->$getter() !== null;
        }

        return true;
    }

    public function __wakeup()
    {
        static::$_snapshot = [];
        foreach ($this as $key => $value) {
            static::$_snapshot[$key] = $value;
        }
    }
}