<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db;

use P3\Db\Command\Delete;
use P3\Db\Command\Insert;
use P3\Db\Command\Select;
use P3\Db\Command\Update;
use P3\Db\Sql;
use P3\Db\Sql\Condition\Where;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Predicate;
use P3\Db\Sql\Predicate\Literal as Literal2;
use P3\Db\Sql\Statement;
use PDO;
use PDOStatement;

use function explode;
use function func_get_args;
use function func_num_args;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function reset;

/**
 * Class Db
 */
class Db
{
    /** @var PDO */
    private $pdo;

    /** @var string */
    private $dsn;

    /** @var string|null */
    private $username;

    /** @var string|null */
    private $password;

    /** @var array */
    private $options = [];

    /** @var string */
    private $charset;

    /** @var Driver */
    private $driver;

    /** @var Driver connection-less driver */
    private $_driver;

    private const DRIVER_CLASS = [
        'mysql'  => Driver\MySql::class,
        'sqlite' => Driver\Sqlite::class,
        'pgsql'  => Driver\PgSql::class,
        'oci'    => Driver\Oci::class,
        'sqlsrv' => Driver\SqlSrv::class,
    ];

    public function __construct(
        string $dsn,
        string $username = null,
        string $password = null,
        array $options = null,
        string $charset = null
    ) {
        $this->dsn = $dsn;
        $this->username = $username;
        $this->password = $password;
        if (!empty($options)) {
            $this->options = $options;
        }
        if (!empty($charset)) {
            $this->charset = $charset;
        }
    }

    /**
     * @param string $driver The PDO driver name ('mysql', 'sqlite', ...)
     * @return bool
     */
    public static function supportsDriver(string $driver): bool
    {
        return !empty(self::DRIVER_CLASS[$driver]);
    }

    private function connect()
    {
        $this->pdo = $this->createPDO();
    }

    public function isConnected(): bool
    {
        return isset($this->pdo);
    }

    public function getPDO(): ?PDO
    {
        return $this->pdo;
    }

    private function pdo(): PDO
    {
        return $this->pdo ?? $this->pdo = $this->createPDO();
    }

    private function createPDO(): PDO
    {
        $pdo = new PDO(
            $this->dsn,
            $this->username,
            $this->password,
            $this->options
        );

        $driver_name = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        switch ($driver_name) {
            // return lowercase column-names in result set for oci-driver
            case 'oci':
                $pdo->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
                break;
            // set charset for pgsql
            case 'pgsql':
                $pdo->exec("SET NAMES '{$this->charset}'");
                break;
        }

        return $pdo;
    }

    /**
     * @return Driver The SQL driver matching the PDO configuration or instance
     */
    public function getDriver(): Driver
    {
        if (isset($this->driver)) {
            return $this->driver;
        }

        if (isset($this->pdo)) {
            // hydrate the pdo-less instance, if any,  with the active pdo connection
            if (isset($this->_driver)) {
                $this->_driver->setPDO($this->pdo);
                return $this->driver = $this->_driver;
            }
            $driver_name = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $driver_fqcn = self::DRIVER_CLASS[$driver_name] ?? Driver\Ansi::class;
            // cache the pdo-aware driver instance
            return $this->driver = new $driver_fqcn();
        }

        if (isset($this->_driver)) {
            return $this->_driver;
        }

        $driver_name = explode(':', $this->dsn)[0];
        $driver_fqcn = self::DRIVER_CLASS[$driver_name] ?? Driver::class;
        // cache the pdo-less driver instance
        return $this->_driver = new $driver_fqcn();
    }

    /**
     * Proxy to PDO::query()
     *
     * @param string $sql The sql-statement
     * @return \PDOStatement|false Execute the statement and returns either a
     *      PDO prepared statement or false on failure
     */
    public function query(string $sql)
    {
        return $this->pdo()->query(...func_get_args());
    }

    /**
     * Proxy to PDO::exec()
     *
     * @param string $sql The DML/DDL/DCL sql-statement
     *
     * @return int|false Execute the statement and returns either the number of
     *      affected rows or false on failure
     */
    public function exec(string $sql)
    {
        return $this->pdo()->exec($sql);
    }

    /**
     * Create and return a new Select command
     *
     * @param array|string|string[]|Literal2|Literal2[]|Select|Select[] $columns
     *      An array of columns with optional key-as-alias or a single column or
     *      the sql-asterisk
     * @param string!Select|null $from The db-table name or a sub-select statement
     * @param string|null $alias The db-table alias
     * @return Select
     */
    public function select($columns = Sql::ASTERISK, $from = null, string $alias = null): Select
    {
        return new Select($this, $columns, $from, $alias);
    }

    /**
     * Fetch a single row for given column value, if any
     *
     * @param string $table
     * @param string $column
     * @param mixed $value
     * @return array|null
     */
    public function fetchOneBy(string $table, string $column, $value): ?array
    {
        return $this->fetchOne(
            $table,
            new Predicate\Comparison($column, '=', $value)
        );
    }

    /**
     * Fetch first rows from a table matching provided criteria, if any
     *
     * @param string $table
     * @param Where|Predicate|array|string $where
     * @param string|array $order
     * @return array|null
     */
    public function fetchOne(string $table, $where = null, $order = null): ?array
    {
        $select = $this->select()->from($table);
        if (isset($where)) {
            $select->where($where);
        }
        if (isset($order)) {
            $select->orderBy($order);
        }

        return $select->fetchOne();
    }

    /**
     * Fetch all the distinct rows from a table using provided criteria
     *
     * @param string $table
     * @param Where|Predicate|array|string $where
     * @param string|array $order
     * @param int $limit
     * @param int $offset
     * @return array[]
     */
    public function fetchAll(
        string $table,
        $where = null,
        $order = null,
        int $limit = null,
        int $offset = null
    ): array {
        $select = $this->select()->from($table);
        if (isset($where)) {
            $select->where($where);
        }
        if (isset($order)) {
            $select->orderBy($order);
        }
        if (isset($limit)) {
            $select->limit($limit);
            if (isset($offset)) {
                $select->offset($offset);
            }
        }

        return $select->fetchAll();
    }

    /**
     * Count the rows from a table matching provided criteria
     *
     * @param string $table
     * @param Where|Predicate|array|string $where
     * @param string $identifier The count indentifier ('*', '1')
     * @return int
     */
    public function count(string $table, $where = null, string $identifier = '*'): int
    {
        $select = $this->select(new Literal("COUNT({$identifier})"), $table);
        if (isset($where)) {
            $select->where($where);
        }

        return (int)$select->fetchColumn(0);
    }

    /**
     * Create a new Insert db-command and either return it or execute it trying
     * to create a new row
     *
     * @param string|null $table
     * @param array<string, mixed> $rowOrRows
     * @return Insert|bool|int
     */
    public function insert(string $table = null, array $rowOrRows = null)
    {
        $insert = new Insert($this, $table);
        if (func_num_args() < 2) {
            return $insert;
        }

        if (empty($rowOrRows)) {
            return 0;
        }

        // multiple rows insert
        if (is_array(reset($rowOrRows))) {
            return $insert->rows($rowOrRows)->execute();
        }

        // single row insert
        $result = $insert->row($rowOrRows, true)->execute();

        return $result === false ? false : ($result > 0);
    }

    /**
     * Create a new Update db-command and either return or execute it
     *
     * @param string|array|null $table
     * @param array|null $data
     * @param string|array|Predicate|Where $where
     *
     * @return Update|false|int
     */
    public function update(string $table = null, array $data = null, $where = null)
    {
        $update = new Update($this, $table);
        if (func_num_args() < 2 || !isset($data)) {
            return $update;
        }

        return $update->set($data)->where($where)->execute();
    }

    /**
     * Create a new Delete db-command and either return or execute it
     *
     * @param string|null $table The db-table to delete from
     * @param string|array|Predicate|Where $where
     * @return Delete|bool|int
     */
    public function delete($table = null, $where = null)
    {
        $delete = new Delete($this, $table);
        if (func_num_args() < 2 || !isset($where)) {
            return $delete;
        }

        return $delete->where($where)->execute();
    }

    /**
     * Prepare a SQL Statement and optionally bind its values returning the
     * prepared/binded PDOStatement
     *
     * @param Statement $statement
     * @param bool $bind Bind statement parameters?
     * @return PDOStatement|false
     */
    public function prepare(Statement $statement, bool $bind_params = false)
    {
        $stmt = $this->pdo()->prepare($statement->getSQL($this->getDriver()));

        if ($bind_params && $stmt instanceof PDOStatement) {
            $params_types = $statement->getParamsTypes();
            foreach ($statement->getParams() as $markerOrIndex => $value) {
                $stmt->bindValue(
                    $markerOrIndex,
                    $this->castValue($value),
                    $params_types[$markerOrIndex] ?? $this->getParamType($value)
                );
            }
        }

        return $stmt;
    }

    public function lastInsertId(string $name = null): string
    {
        return $this->pdo()->lastInsertId($name);
    }

    private function castValue($value)
    {
        if (is_bool($value)) {
            return (int)$value;
        }
        if (is_float($value)) {
            $value = (string)$value;
        }

        return $value;
    }

    private function getParamType($value): int
    {
        if (null === $value) {
            return PDO::PARAM_NULL;
        }
        if (is_int($value) || is_bool($value)) {
            return PDO::PARAM_INT;
        }

        return PDO::PARAM_STR;
    }

    public function beginTransaction(): bool
    {
        return $this->pdo()->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo()->commit();
    }

    public function rollBack(): bool
    {
        return $this->pdo()->rollBack();
    }
}
