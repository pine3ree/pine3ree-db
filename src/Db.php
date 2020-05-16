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
use P3\Db\Sql\Clause\Where;
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

    /** @var string */
    private const DEFAULT_CHARSET = 'utf8';

    /** @var Driver the sql-driver */
    private $driver;

    /** @var Driver connection-less sql-driver */
    private $_driver;

    /**
     * @const array<string, string> An pdo-driver-name to sql-driver-class map
     */
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
        array $options = null
    ) {
        $this->dsn = $dsn;
        $this->username = $username;
        $this->password = $password;
        if (!empty($options)) {
            $this->options = $options;
        }
        $this->charset = $options['charset'] ?? self::DEFAULT_CHARSET;
    }

    /**
     * @param string $driver The PDO driver name ('mysql', 'sqlite', ...)
     * @return bool
     */
    public static function supportsDriver(string $driver): bool
    {
        return !empty(self::DRIVER_CLASS[$driver]);
    }

    public function isConnected(): bool
    {
        return isset($this->pdo);
    }

    private function connect()
    {
        if (!isset($this->pdo)) {
            $this->pdo = $this->createPDO();
        }
    }

    private function reconnect()
    {
        $this->pdo = null;
        $this->pdo = $this->createPDO();
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
            // set charset for pgsql: since charset is a developer written
            // configuration value there is no need for escaping
            case 'pgsql':
                $pdo->exec("SET NAMES '{$this->charset}'");
                break;
        }

        return $pdo;
    }

    /**
     * Return the SQL driver matching the PDO configuration or instance
     *
     * @return Driver
     */
    public function getDriver(): Driver
    {
        if (isset($this->driver)) {
            return $this->driver;
        }

        if (isset($this->pdo)) {
            // hydrate and reuse the pdo-less instance, if any, with the active
            // pdo connection
            if (isset($this->_driver)) {
                $this->_driver->setPDO($this->pdo);
                return $this->driver = $this->_driver;
            }
            $driver_name = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            // cache the pdo-aware driver instance
            $this->driver = $this->createDriverFromName($driver_name);
            return $this->driver;
        }

        if (isset($this->_driver)) {
            return $this->_driver;
        }

        $driver_name = explode(':', $this->dsn)[0];
        // cache the pdo-less driver instance
        $this->_driver = $this->createDriverFromName($driver_name);
        return $this->_driver;
    }

    private function createDriverFromName(string $driver_name): Driver
    {
        $driver_fqcn = self::DRIVER_CLASS[$driver_name] ?? null;
        if (empty($driver_fqcn) || !is_subclass_of($driver_fqcn, Driver::class, true)) {
            return Driver::ansi();
        }

        return new $driver_fqcn($this->pdo);
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
     * to create a new row or multiple new rows
     *
     * @param string|null $table
     * @param array<string, mixed> $row_or_rows A new table row/row-set
     * @return Insert|bool|int
     */
    public function insert(string $table = null, array $row_or_rows = null)
    {
        $insert = new Insert($this, $table);
        if (func_num_args() < 2) {
            return $insert;
        }

        if (empty($row_or_rows)) {
            return 0;
        }

        // multiple rows insert: returns int|false
        if (is_array(reset($row_or_rows))) {
            return $insert->rows($row_or_rows)->execute();
        }

        // single row insert: returns bool
        $result = $insert->row($row_or_rows, true)->execute();

        return $result === false ? false : ($result > 0);
    }

    /**
     * Create a new Update db-command and either return or execute it
     *
     * @param string|null $table
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
    public function delete(string $table = null, $where = null)
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
     * @param bool $bind_params Bind statement parameters values (via PDOStatement::bindValue())?
     * @return PDOStatement|false
     */
    public function prepare(Statement $statement, bool $bind_params = false)
    {
        $stmt = $this->pdo()->prepare($statement->getSQL($this->getDriver()));

        if ($bind_params && $stmt instanceof PDOStatement) {
            $types = $statement->getParamsTypes();
            foreach ($statement->getParams() as $index => $value) {
                $stmt->bindValue(
                    $index, // string marker (:name) or the 1-indexed position
                    $value,
                    $types[$index] ?? $this->getParamType($value)
                );
            }
        }

        return $stmt;
    }

    private function getParamType($value): int
    {
        if (null === $value) {
            return PDO::PARAM_NULL;
        }
        // use int-type for bool:
        // @see https://bugs.php.net/bug.php?id=38386
        // @see https://bugs.php.net/bug.php?id=49255
        if (is_int($value) || is_bool($value)) {
            return PDO::PARAM_INT;
        }

        return PDO::PARAM_STR;
    }

    /**
     * Proxy to PDO::query()
     *
     * @see \PDO::query()
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
     * @see \PDO::exec()
     * @param string $sql The DML/DDL/DCL statement sql-string
     * @return int|false Execute the statement and returns either the number of
     *      affected rows or false on failure
     */
    public function exec(string $sql)
    {
        return $this->pdo()->exec($sql);
    }

    /**
     * Return the last-inserted value
     *
     * @param string $name The sequence name, if any
     * @return string
     */
    public function lastInsertId(string $name = null): string
    {
        if (isset($this->pdo)) {
            return $this->pdo->lastInsertId($name);
        }

        return '';
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
