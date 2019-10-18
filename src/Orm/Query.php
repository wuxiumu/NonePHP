<?php

namespace NonePHP\Orm;

use NonePHP\Exception\DatabaseException;

class Query
{
    protected $_modelName;
    protected $_sql;
    protected $_fields = '*';
    protected $_conditions = '';
    protected $_bindParams = [];
    protected $_bindTypes = [];
    protected $_order = '';
    protected $_limit = 100;
    protected $_offset = 0;
    protected $_type = '';

    public function __construct(string $modelName)
    {
        $this->_modelName = $modelName;
    }

    public function where(string $conditions, array $bindParams = [], array $bindTypes = [])
    {
        if (empty($this->_conditions)) {
            $this->_conditions = $conditions;
        } else {
            $this->_conditions .= ' AND ' . $conditions;
        }

        if (empty($this->_bindParams)) {
            $this->_bindParams = $bindParams;
        } else {
            $this->_bindParams = array_merge($this->_bindParams, $bindParams);
        }

        if (empty($this->_bindTypes)) {
            $this->_bindTypes = $bindTypes;
        } else {
            $this->_bindTypes = array_merge($this->_bindTypes, $bindTypes);
        }

        return $this;
    }

    public function andWhere(string $conditions, array $bindParams = [], array $bindTypes = []): Query
    {
        if (!empty($this->_conditions)) {
            $conditions = ' AND (' . $conditions . ')';
        }
        return $this->where($conditions, $bindParams, $bindTypes);
    }

    public function orWhere(string $conditions, array $bindParams = [], array $bindTypes = []): Query
    {
        if (!empty($this->_conditions)) {
            $conditions = ' OR (' . $conditions . ')';
        }
        return $this->where($conditions, $bindParams, $bindTypes);
    }

    public function orderBy($order)
    {
        $this->_order = $order;
        return $this;
    }

    public function limit(int $limit, $offset = null)
    {
        if ($limit === 0) {
            return $this;
        }
        $this->_limit = abs($limit);
        if (is_numeric($offset)) {
            $this->_offset = abs($offset);
        }

        return $this;
    }

    public function getLimit(): int
    {
        return $this->_limit;
    }

    public function offset(int $offset = 0)
    {
        if (!$offset) {
            $this->_offset = abs($offset);
        }
        return $this;
    }

    public function getOffset(): int
    {
        return $this->_offset;
    }

    public function fields(string $fields): void
    {
        $this->_fields = $fields;
    }

    public function buildRaw(string $sql)
    {
        $this->_sql = $sql;
        $this->_sql = trim($this->_sql);
        $this->_type = substr($this->_sql, 0, strpos($this->_sql, ' ', 1));
        $this->_type = strtolower($this->_type);
        return $this;
    }

    public function buildSelect(string $tableName)
    {
        $this->_sql = 'SELECT ' . implode(',', array_map(static function ($v) {
             return "`$v`";
            }, explode(',', $this->_fields)));
        $this->_sql .= ' FROM ' . $tableName;
        if ($this->_conditions) {
            $this->_sql .= ' WHERE ' . $this->_conditions;
        }
        if ($this->_order) {
            $this->_sql .= ' ORDER BY ' . $this->_order;
        }
        if ($this->_limit) {
            $this->_sql .= ' LIMIT ' . $this->_limit;
        }
        if ($this->_offset) {
            $this->_sql .= ' OFFSET ' . $this->_offset;
        }
        $this->_type = 'select';
        return $this;
    }

    public function buildInsert(string $tableName, array $columns, array $values)
    {
        $this->_sql = 'INSERT';
        $this->_sql .= ' INTO ' . $tableName . '(';
        foreach ($columns as $column) {
            $this->_sql .= '`' . $column . '`,';
        }
        $this->_sql = rtrim($this->_sql, ',');
        $this->_sql .= ') VALUE(';
        foreach ($columns as $column) {
            if (!isset($values[$column])) {
                throw (new DatabaseException())->debug('cannot found bind value for ' . $column);
            }
            $this->_sql .= ':' . $column . ',';
            $this->_bindParams[':' . $column] = $values[$column];
        }
        $this->_sql = rtrim($this->_sql, ',');
        $this->_sql .= ')';
        $this->_type = 'insert';
        return $this;
    }

    public function buildUpdate(string $tableName, array $changed, array $lockColumns)
    {
        $this->_sql = 'UPDATE ' . $tableName . ' SET ';
        foreach ($changed as $column => $value) {
            $this->_sql .= '`' . $column . '` = :' . $column . ',';
            $this->_bindParams[':' . $column] = $value;
        }

        $this->_sql = rtrim($this->_sql, ',');
        $this->_sql .= ' WHERE ';
        foreach ($lockColumns as $column => $value) {
            $this->_sql .= '`' . $column . '` = :lock' . $column;
            $this->_bindParams[':lock' . $column] = $value;
        }

        $this->_type = 'update';
        return $this;
    }

    public function getType(): string
    {
        return $this->_type;
    }

    public function getSQL()
    {
        return $this->_sql;
    }

    public function getBindParams(): array
    {
        return $this->_bindParams;
    }

    public function getBindTypes(): array
    {
        return $this->_bindTypes;
    }
}