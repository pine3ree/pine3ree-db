<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db;

use PDO;
use PDOStatement;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Predicate;
use P3\Db\Sql\Statement;
use P3\Db\Sql\Statement\Delete;
use P3\Db\Sql\Statement\Insert;
use P3\Db\Sql\Statement\Select;
use P3\Db\Sql\Statement\Update;
use P3\Db\Sql\Condition\Where;

use function func_num_args;
use function is_array;
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

    /** @var Driver */
    private $driver;

    private const DRIVER_CLASS = [
        'mysql'  => Driver\MySql::class,
        'sqlite' => Driver\Sqlite::class,
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
        if (isset($options)) {
            $this->options = $options;
        }
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
        return new PDO(
            $this->dsn,
            $this->username,
            $this->password,
            $this->options
        );
    }

    public function getDriver(): Driver
    {
        if (isset($this->driver)) {
            return $this->driver;
        }

        if (isset($this->pdo)) {
            $driver_name = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $driver_fqcn = self::DRIVER_CLASS[$driver_name] ?? Driver::class;
            // cache the driver instance
            return $this->driver = new $driver_fqcn();
        }

        $driver_name = explode(':', $this->dsn)[0];
        $driver_fqcn = self::DRIVER_CLASS[$driver_name] ?? Driver::class;
        // do not cache the driver instance
        return new $driver_fqcn();
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

        $stmt = $this->prepare($select, true);
        if (false === $stmt || false === $stmt->execute()) {
            return null;
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return is_array($row) ? $row : null;
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
        $select = $this->select()->distinct()->from($table);

        if (isset($where)) {
            $select->where($where);
        }
        if (isset($order)) {
            $select->orderBy($order);
        }
        if (isset($limit)) {
            $select->limit($limit);
            if (isset($limit)) {
                $select->offset($offset);
            }
        }

        $stmt = $this->prepare($select, true);
        if (false === $stmt || false === $stmt->execute()) {
            return false;
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $rows;
    }

    /**
     * Create and return a new Select statement
     *
     * @param array|string $columns An array of columns with optional key-as-alias,
     *      a column or the asterisk
     * @param string|array|null $table
     * @return Select
     */
    public function select($columns = Sql::ASTERISK, $table = null): Select
    {
        return new Select($columns, $table);
    }

    /**
     * Create an Insert statement and either return it run it if additional
     * arguments are provided
     *
     * @param string|null $table
     * @param array[] $rows
     * @return Insert|false|int
     */
    public function insert(string $table = null, array $rows = null)
    {
        if (func_num_args() < 2) {
            return new Insert($table);
        }

        $insert = new Insert();
        $insert
            ->into($table)
            ->rows($rows);

        $stmt = $this->prepare($insert, true);
        if (false === $stmt || false === $stmt->execute()) {
            return false;
        }

        return $stmt->rowCount();
    }

    /**
     * Insert a new row/record into the a db-table
     *
     * @param string $table
     * @param array $row
     *
     * @return bool
     */
    public function insertRow(string $table, array $row): bool
    {
        $insert = new Insert();
        $insert
            ->into($table)
            ->row($row);

        $stmt = $this->prepare($insert, true);
        if (false === $stmt || false === $stmt->execute()) {
            return false;
        }

        return $stmt->rowCount() > 0;
    }

    /**
     * Create an Update statement and either return it run it if additional
     * arguments are provided
     *
     * @param string|array|null $table
     * @param array|null $data
     * @param string|array|Predicate|Where $where
     *
     * @return Update|false|int
     */
    public function update(string $table = null, array $data = null, $where = null)
    {
        $update = new Update($table);

        if (func_num_args() < 2 || !isset($data)) {
            return $update;
        }

        $update
            ->set($data)
            ->where($where);

        $stmt = $this->prepare($update, true);
        if (false === $stmt || false === $stmt->execute()) {
            return false;
        }

        return $stmt->rowCount();
    }

    /**
     * Create and return a new Select statement
     *
     * @param string|array|null $table
     * @param string|array|Predicate|Where $where
     * @return Delete|bool|int
     */
    public function delete($from = null, $where = null)
    {
        $delete = new Delete($from);

        if (func_num_args() < 2 || !isset($where)) {
            return $delete;
        }

        $delete->where($where);

        $stmt = $this->prepare($delete, true);
        if (false === $stmt || false === $stmt->execute()) {
            return false;
        }

        return $stmt->rowCount();
    }

    /**
     * Prepare a Statement and optionally bind its values returning the
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
