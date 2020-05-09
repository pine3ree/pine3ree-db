<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db;

use PDO;
use PDOStatement;
use P3\Db\Command\Select;
use P3\Db\Command\Insert;
use P3\Db\Command\Update;
use P3\Db\Command\Delete;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Predicate;
use P3\Db\Sql\Statement;
use P3\Db\Sql\Condition\Where;

use function func_num_args;
use function is_int;

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
        if (isset($options)) {
            $this->options = $options;
        }
        if (isset($charset)) {
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

    public function getPDO(): PDO
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

    public function getDriver(): Driver
    {
        if (isset($this->driver)) {
            return $this->driver;
        }

        if (isset($this->pdo)) {
            if (isset($this->_driver)) {
                $this->_driver->setPDO($this->pdo);
                return $this->driver = $this->_driver;
            }
            $driver_name = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $driver_fqcn = self::DRIVER_CLASS[$driver_name] ?? Driver::class;
            // cache the driver instance
            return $this->driver = new $driver_fqcn();
        }

        if (isset($this->_driver)) {
            return $this->_driver;
        }

        $driver_name = explode(':', $this->dsn)[0];
        $driver_fqcn = self::DRIVER_CLASS[$driver_name] ?? Driver::class;
        // do not cache the driver instance
        return $this->_driver = new $driver_fqcn();
    }

    /**
     * Create and return a new Select command
     *
     * @param array|string $columns An array of columns with optional key-as-alias,
     *      a column or the asterisk
     * @param string|null $table The db-table name
     * @param string|null $alias The db-table alias
     * @return Select
     */
    public function select($columns = Sql::ASTERISK, string $table = null, string $alias = null): Select
    {
        return new Select($this, $columns, $table, $alias);
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
        $select->limit(1);

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
     * @return int|null
     */
    public function count(string $table, $where = null): ?int
    {
        $select = $this->select();
        $select
            ->columns([
                new Sql\Literal("COUNT(*)"),
            ])
            ->from($table);

        if (isset($where)) {
            $select->where($where);
        }

        return $select->fetchColumn(0);
    }

    /**
     * Create a new Insert db-command and either return it or execute it trying
     * to create a new row
     *
     * @param string|null $table
     * @param array<string, mixed> $row
     * @return Insert|bool
     */
    public function insert(string $table = null, array $row = null)
    {
        $insert = new Insert($this, $table);
        if (func_num_args() < 2) {
            return $insert;
        }

        $result = $insert->row($row)->execute();

        if (false === $result) {
            return false;
        }

        return $result > 0;
    }

    /**
     * Insert multiple rows into the given db-table
     *
     * @param string $table
     * @param array<string, mixed>[] $rows
     *
     * @return int|false The number of inserted rows or false on failure
     */
    public function insertRows(string $table, array $rows)
    {
        return (new Insert($this, $table))->rows($rows)->execute();
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
     * @param string|array|null $table
     * @param string|array|Predicate|Where $where
     * @return Delete|bool|int
     */
    public function delete($from = null, $where = null)
    {
        $delete = new Delete($this, $from);
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
        $stmt = $this->getPDO()->prepare($statement->getSQL($this->getDriver()));

        if ($bind_params && $stmt instanceof PDOStatement) {
            $params_types = $statement->getParamsTypes();
            foreach ($statement->getParams() as $markerOrIndex => $value) {
                $stmt->bindValue(
                    $markerOrIndex,
                    $this->castValue($value),
                    $params_types[$key] ?? $this->getParamType($value)
                );
            }
        }

        return $stmt;
    }

    public function lastInsertId(string $name = null): string
    {
        return $this->getPDO()->lastInsertId($name);
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
        return $this->getPDO()->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->getPDO()->commit();
    }

    public function rollBack(): bool
    {
        return $this->getPDO()->rollBack();
    }
}
