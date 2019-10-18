<?php

/**
 * 只支持 MySQL PDO
 */

namespace NonePHP\Orm;

use NonePHP\Exception\DatabaseException;
use PDOException;
use PDOStatement;

class Pdo
{
    /** @var \PDO $_pdo */
    protected $_pdo;
    protected $_dsn;
    protected $_username;
    protected $_password;
    protected $_options;

    public function __construct(array $config = [])
    {
        $this->_dsn = 'mysql:host=';
        $this->_dsn .= $config['host'] ?? 'localhost';
        $this->_dsn .= ';port=' . ($config['port'] ?? '3306');
        $this->_dsn .= ';dbname=' . ($config['dbname'] ?? '');
        $this->_username = $config['username'] ?? 'root';
        $this->_password = $config['password'] ?? '';
        $this->_options = array_diff_key($config, [
            'host' => '',
            'port' => '',
            'dbname' => '',
            'username' => '',
            'password' => '',
        ]);
        $this->connect();
    }

    public function connect(): void
    {
        try {
            $this->_pdo = new \PDO($this->_dsn, $this->_username, $this->_password,  $this->_options);
            $this->_pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
            $this->_pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw (new DatabaseException())->debug($e->getMessage());
        }
    }

    /**
     * @param string $sql
     * @return bool|PDOStatement
     */
    public function prepare(string $sql)
    {
        return $this->_pdo->prepare($sql);
    }

    public function executePrepared(PDOStatement $statement, array $bindParams, array $bindTypes = []): PDOStatement
    {
        foreach ($bindParams as $key => $value) {
            if (is_scalar($value)) {
                $parameter = is_int($key) ? $key + 1 : $key;
                if (isset($bindTypes[$key])) {
                    $statement->bindValue($parameter, $value, $bindTypes[$key]);
                } else {
                    $statement->bindValue($parameter, $value);
                }
            }
        }
        $statement->execute();
        return $statement;
    }

    public function query(string $sql, array $bindParams = [])
    {
        $result = false;
        if (!empty($bindParams)) {
            $statement = $this->prepare($sql);
            if ($statement instanceof PDOStatement) {
                $result = $this->executePrepared($statement, $bindParams);
            }
        } else {
            $result = $this->_pdo->query($sql);
        }

        return $result;
    }


    public function _getPdo(): \PDO
    {
        return $this->_pdo;
    }


    public function __destruct()
    {
        $this->_pdo = null;
    }
}