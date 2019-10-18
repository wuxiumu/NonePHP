<?php

/**
 * SPL - Standard PHP Library（PHP标准库）
 * 目前的主要功能是， 使Object有Array的行为的接口，主要是：
 * 1. Iterator 提供可以foreach对象（数据库结果集）功能
 *      rewind(), valid(), { 1. current(), key() }, { 2. next(), valid() }, {1} , {2}
 * 2. Countable, ArrayAccess
 */

namespace NonePHP\Orm;

use ArrayAccess;
use Iterator;
use JsonSerializable;
use PDOStatement;
use RuntimeException;
use stdClass;

class Result implements Iterator, ArrayAccess, JsonSerializable
{
    /** @var Model $_model */
    protected $_model;
    protected $_pdo;
    protected $_query;
    protected $_bindParams;
    protected $_bindTypes;

    protected $_count = 0;      // 记录行数
    protected $_rows = [];      // 记录实体,所有值
    protected $_row;            // 当前记录行
    protected $_pointer = 0;    // 当前位置
    protected $_rowData = [];   // 当前行数据

    public function __construct( Query $query, PDOStatement $pdo, Model $model )
    {
        $this->_model = $model;
        $this->_query = $query->getSQL();
        $this->_bindParams = json_encode($query->getBindParams());
        $this->_bindTypes = json_encode($query->getBindTypes());
        $this->_pdo = $pdo;
        if (!$pdo) {
            return;
        }
        $pdo->setFetchMode(\PDO::FETCH_ASSOC);
        $this->_count = $pdo->rowCount();
        if ($this->_count === 0) {
            return;
        }
        if ($this->_count <= 10) {
            $this->_rows = $pdo->fetchAll();
        }
    }

    public function count()
    {
        return $this->_count;
    }

    public function rewind()
    {
        $this->_pointer = 0;
        $this->seek($this->_pointer);
    }

    public function valid()
    {
        return $this->_pointer >= 0 && $this->_count >= 0 && $this->_pointer <= $this->_count && !empty($this->_rowData);
    }

    public function current()
    {
        /** @var Model $_class */
        $_class = get_class($this->_model);
        $this->_row = $_class::_cloneResult($this->_rowData);
        $this->_rowData = [];
        if (is_callable([$this->_row, 'afterQuery'])) {
            $this->_row->afterQuery();
        }
        return $this->_row;
    }

    public function next()
    {
        $this->seek(++$this->_pointer);
    }


    public function key()
    {
        return $this->_pointer;
    }

    public function offsetGet($offset)
    {
        if ($offset > $this->_count) {
            return new stdClass();
        }

        $this->seek($offset);
        if (!$this->valid()) {
            return new stdClass();
        }
        return $this->current();
    }

    public function offsetSet($offset, $value)
    {
        throw new RuntimeException('暂时未实现 offsetSet');
    }

    public function offsetExists($offset)
    {
        $this->seek($offset);
        return $this->valid();
    }

    public function offsetUnset($offset)
    {
        throw new RuntimeException('暂时未实现 offsetUnset');
    }

    public function toArray()
    {
        if (empty($this->_rows)) {
            $this->_rows = $this->_pdo->fetchAll();
        }

        return $this->_rows;
    }

    public function __sleep()
    {
        $this->rewind();
        return [
            '_model', '_query', '_bindParams', '_bindTypes', '_count', '_rows', '_row', '_pointer', '_rowData'
        ];
    }

    public function __wakeup()
    {
        $_conn = $this->_pdo = $this->_model->_getDbConnection('read');
        $this->_pdo = $_conn->executePrepared($_conn->prepare($this->_query), json_decode($this->_bindParams, true),
            json_decode($this->_bindTypes, true));
    }


    public function jsonSerialize()
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function seek($num): void
    {
        if (empty($this->_rowData)) {
            if ($this->_rows && count($this->_rows) - 1 >= $num) {
                $this->_rowData = $this->_rows[$num] ?? [];
                return;
            }

            $this->_rowData = $this->_pdo->fetch();
            $this->_rows[] = $this->_rowData;
        }
    }
}