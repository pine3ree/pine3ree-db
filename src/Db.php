<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\Db;

use pine3ree\Db\Exception\InvalidArgumentException;
use pine3ree\Db\Command\Delete;
use pine3ree\Db\Command\Insert;
use pine3ree\Db\Command\Select;
use pine3ree\Db\Command\Update;
use pine3ree\Db\Sql;
use pine3ree\Db\Sql\Alias;
use pine3ree\Db\Sql\Clause\Where;
use pine3ree\Db\Sql\Driver;
use pine3ree\Db\Sql\DriverInterface;
use pine3ree\Db\Sql\Identifier;
use pine3ree\Db\Sql\Literal;
use pine3ree\Db\Sql\Predicate;
use pine3ree\Db\Sql\Statement as SqlStatement;
use pine3ree\Db\Sql\Statement\Select as SqlSelect;
use PDO;
use PDOStatement;
use pine3ree\Db\Exception\RuntimeException;

use function explode;
use function func_get_args;
use function func_num_args;
use function get_class;
use function gettype;
use function is_array;
use function is_bool;
use function is_int;
use function is_object;
use function is_subclass_of;
use function reset;
use function sprintf;

class Db
{
    /** The wrapped PDO instance */
    private ?PDO $pdo = null;

    /** @var The PDO connection data source name */
    private ?string $dsn = null;

    /** The PDO connection username */
    private ?string $username = null;

    /** @var The PDO connection password */
    private ?string $password = null;

    /** PDO connection options */
    private array $options;

    /** The connection charset */
    private string $charset;

    /** @var string The default connection charset */
    private const DEFAULT_CHARSET = 'utf8';

    /** @var The PDO class to use */
    private string $pdoClass = PDO::class;

    /** A connection-aware sql-driver instance */
    private ?DriverInterface $driver = null;

    /** @var DriverInterface|null A connection-less sql-driver instance */
    private $_driver;

    /** bool Has the pdo instance been initialized? */
    private bool $pdoIsInitialized = false;

    /** Keep track of the current nesting transaction depth */
    private int $transactionLevel = 0;

    /** Flag for rollback operation currently active */
    private bool $inRollBack = false;

    /**
     * @param string|PDO $dsn_or_pdo A valid pdo dsn string or an existing pdo connection instance
     * @param string|null $username PDO connection username
     * @param string|null $password PDO connection password
     * @param array|null $options PDO connection options
     * @param string|null $pdoClass An optional PDO subclass to use when creating a new connection
     */
    public function __construct(
        $dsn_or_pdo,
        string $username = null,
        string $password = null,
        array $options = null,
        string $pdoClass = null
    ) {
        if (is_string($dsn_or_pdo)) {
            $this->dsn      = $dsn_or_pdo;
            $this->username = $username;
            $this->password = $password;
            if (isset($pdoClass)) {
                if (!is_subclass_of($pdoClass, PDO::class, true)) {
                    throw new InvalidArgumentException(
                        "The pdoClass argument is not a PDO subclass!"
                    );
                }
                $this->pdoClass = $pdoClass;
            }
        } elseif ($dsn_or_pdo instanceof PDO) {
            $this->pdo = $dsn_or_pdo;
        } else {
            throw new InvalidArgumentException(sprintf(
                '$dsn_or_pdo must be either a dns string or a PDO instance, `%s` provided!',
                is_object($dsn_or_pdo) ? get_class($dsn_or_pdo) : gettype($dsn_or_pdo)
            ));
        }

        $this->charset = $options['charset'] ?? self::DEFAULT_CHARSET;
        $this->options = $options ?? [];
        $this->decorateOptions();
    }

    public function __destruct()
    {
        $this->pdo = null;
    }

    private function decorateOptions(): void
    {
        if (empty($this->dsn)) {
            return;
        }

        $driver_name = explode(':', $this->dsn)[0];
        switch ($driver_name) {
            // Return lowercase column-names in result set for oci-driver
            case 'oci':
                $this->options[PDO::ATTR_CASE] = PDO::CASE_LOWER;
                break;
        }
    }

    public function isConnected(): bool
    {
        return isset($this->pdo);
    }

    private function connect(bool $force_reconnection = false): void
    {
        if ($force_reconnection || !isset($this->pdo)) {
            $this->pdoIsInitialized = false;
            $this->pdo = $this->createPDO();
            $this->initializePDO();
            $this->transactionLevel = 0;
            $this->inRollBack = false;
        }
    }

    private function disconnect(): void
    {
        $this->pdo = null;
        $this->transactionLevel = 0;
        $this->inRollBack = false;
    }

    private function reconnect(): void
    {
        if (empty($this->dsn)) {
            throw new RuntimeException(
                "Cannot reconnect without a dsn!"
            );
        }

        $this->disconnect();
        $this->connect();
    }

    /**
     * Get the active pdo instance, if any, optionally forcing its instantiation
     *
     * @param bool $instantiate Create a new PDO connection if not already connected?
     * @return PDO|null
     */
    public function getPDO(bool $instantiate = false): ?PDO
    {
        if (isset($this->pdo)) {
            $this->pdoIsInitialized || $this->initializePDO();
            return $this->pdo;
        }

        if ($instantiate) {
            $this->connect();
            return $this->pdo;
        }

        return null;
    }

    private function pdo(): PDO
    {
        if (isset($this->pdo)) {
            $this->pdoIsInitialized || $this->initializePDO();
            return $this->pdo;
        }

        $this->connect();

        return $this->pdo;
    }

    private function createPDO(): PDO
    {
        if (empty($this->dsn)) {
            throw new RuntimeException(
                "A PDO instance was passed in the constructor: there is no DNS"
                . " string available to create a new connection!"
            );
        }

        $pdoClass = $this->pdoClass;

        $pdo = new $pdoClass(
            $this->dsn,
            $this->username,
            $this->password,
            $this->options
        );

        return $pdo;
    }

    /**
     * Perform initialization commands when required based on the connecion driver
     *
     * @return bool
     */
    private function initializePDO(): bool
    {
        if ($this->pdoIsInitialized) {
            return true;
        }

        if (!isset($this->pdo)) {
            return false;
        }

        // Set attributes if a PDO instance was passed in
        if (empty($this->dsn)) {
            foreach ($this->options as $attribute => $value) {
                $this->pdo->setAttribute($attribute, $value);
            }
        }

        switch ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            // Set charset for pgsql: since charset is a developer written
            // configuration value there is no need for escaping
            case 'pgsql':
                $this->pdo->exec("SET NAMES '{$this->charset}'");
                break;
        }

        $this->pdoIsInitialized = true;
        return true;
    }

    /**
     * Return the SQL driver matching the PDO configuration or instance
     *
     * @param bool $with_pdo Return a driver instance with an active connection?
     * @return DriverInterface
     */
    public function getDriver(bool $with_pdo = false): DriverInterface
    {
        if (isset($this->driver)) {
            return $this->driver;
        }

        $pdo = $with_pdo ? $this->pdo() : $this->pdo;

        if (isset($pdo)) {
            // Inject and reuse the pdo-less instance, if any, with the active
            // pdo connection
            if (isset($this->_driver)) {
                $this->_driver->setPDO($pdo);
                return $this->driver = $this->_driver;
            }

            // create and cache the pdo-aware driver instance
            $driver_name = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $this->driver = $this->createDriverFromName($driver_name, $pdo);
            return $this->driver;
        }

        if (isset($this->_driver)) {
            return $this->_driver;
        }

        $driver_name = explode(':', $this->dsn)[0];
        // Cache the pdo-less driver instance
        $this->_driver = $this->createDriverFromName($driver_name);
        return $this->_driver;
    }

    private function createDriverFromName(string $driver_name, PDO $pdo = null): Driver
    {
        $driver_fqcn = Driver::SUPPORTED[$driver_name] ?? null;
        if (empty($driver_fqcn) || !is_subclass_of($driver_fqcn, Driver::class, true)) {
            return Driver::ansi();
        }

        return new $driver_fqcn($pdo);
    }

    /**
     * Create and return a new Select command
     *
     * @param array|string|string[]|Literal|Literal[]|SqlSelect|SqlSelect[] $columns
     *      An array of columns with optional key-as-alias or a single column or
     *      the sql-asterisk
     * @param string|SqlSelect|null $from The db-table name or a sub-select statement
     * @param string|null $alias The db-table alias
     * @return Select
     */
    public function select($columns = null, $from = null, string $alias = null): Select
    {
        return new Select($this, $columns, $from, $alias);
    }

    /**
     * Fetch a single row for given column value, if any
     *
     * @param string $table
     * @param string $column
     * @param scalar|Literal|Identifier|Alias|null $value
     * @param string|array $order
     * @return array|null
     */
    public function fetchOneBy(string $table, string $column, $value, $order = null): ?array
    {
        return $this->fetchOne(
            $table,
            new Predicate\Comparison($column, '=', $value),
            $order
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

        return $select->fetchOne(PDO::FETCH_ASSOC);
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
        }
        if (isset($offset) && $offset > 0) {
            $select->offset($offset);
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
    public function count(string $table, $where = null, string $identifier = Sql::ASTERISK): int
    {
        $select = $this->select()->count($identifier)->from($table);
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
     * @param array|array[] $row_or_rows A new table row/row-set
     * @psalm-param array<string, mixed>|array<string, mixed>[] $row_or_rows A new table row/row-set
     * @return Insert|int|bool
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

        // Multiple rows insert: returns int|false
        if (is_array(reset($row_or_rows))) {
            return $insert->rows($row_or_rows)->execute();
        }

        // Single row insert: returns bool
        $result = $insert->row($row_or_rows, true)->execute();

        return $result === false ? false : ($result > 0);
    }

    /**
     * Create a new Update db-command and either return or execute it
     *
     * @param string|null $table
     * @param array|null $data
     * @param string|array|Predicate|Where $where
     * @return Update|false|int
     */
    public function update(string $table = null, array $data = null, $where = null)
    {
        $update = new Update($this, $table);
        if (func_num_args() < 2 || !isset($data)) {
            return $update;
        }

        if (empty($data)) {
            throw new InvalidArgumentException(
                "Cannot perform an UPDATE with empty data!"
            );
        }

        if (empty($where)) {
            throw new InvalidArgumentException(
                "Cannot perform an UPDATE without conditions!"
            );
        }

        return $update->set($data)->where($where)->execute();
    }

    /**
     * Create a new Delete db-command and either return or execute it
     *
     * @param string|null $table The db-table to delete from
     * @param string|array|Predicate|Where $where
     * @return Delete|false|int
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
     * @param SqlStatement $sqlStatement
     * @param bool $bind_values Bind the statement parameters values (via PDOStatement::bindValue())?
     * @return PDOStatement|false
     */
    public function prepare(SqlStatement $sqlStatement, bool $bind_values = false)
    {
        $stmt = $this->pdo()->prepare($sqlStatement->getSQL(
            $this->driver ?? $this->getDriver(true)
        ));

        if ($bind_values
            && $stmt instanceof PDOStatement
            && $sqlStatement->hasParams()
        ) {
            $params = $sqlStatement->getParams();
            $types = $params->getTypes();
            foreach ($params->getValues() as $index => $value) {
                $stmt->bindValue(
                    $index, // unique string marker (:name) or 1-indexed position
                    $value,
                    $types[$index] ?? $this->getParamType($value)
                );
            }
        }

        return $stmt;
    }

    /**
     * Return the most appropriate PDO param-type constant for the given value
     *
     * @param mixed $value
     * @return int
     */
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
     * @see PDO::query()
     * @param string $sql The sql-statement string
     * @param int|null $fetch_mode The default fetch mode for the returned PDOStatement.
     *      It must be one of the PDO::FETCH_* constants.
     * @return PDOStatement|false Execute the statement and returns either an
     *      already executed traversable PDOStatement object or false on failure.
     *      The PDO statement object can be iterated over to fetch the result-set
     *      rows, if any.
     */
    public function query(string $sql, ?int $fetch_mode = null)
    {
        return $this->pdo()->query(...func_get_args());
    }

    /**
     * Proxy to PDO::exec()
     *
     * @see PDO::exec()
     * @param string $sql The DML/DDL/DCL statement sql-string
     * @return int|false Execute the statement and returns either the number of
     *      affected rows or false on failure
     */
    public function exec(string $sql)
    {
        return $this->pdo()->exec($sql);
    }

    /**
     * Return the last-inserted value for the active connection, NULL if not
     * connected or FALSE if the PDO driver does not support this method
     *
     * Some drivers such as pgsql requires a sequence name
     *
     * In a multi-INSERT statement, the mysql/mariadb driver will return the
     * AUTO_INCREMENT value generated for the first row inserted
     *
     * @param string|null $name The sequence name, if any
     * @return string|false|null
     */
    public function lastInsertId(?string $name = null)
    {
        if (isset($this->pdo)) {
            return $this->pdo->lastInsertId($name);
        }

        return null;
    }

    /**
     * Try to actually start a PDO transaction or just increase the transaction
     * nesting level counter
     *
     * Returns TRUE if actually starting a pdo transaction
     *
     * @throws RuntimeException If a transaction was already initiated but not by this Db instance
     */
    public function beginTransaction(): bool
    {
        if ($this->transactionLevel === 0) {
            $pdo = $this->pdo();
            if ($pdo->inTransaction()) {
                throw new RuntimeException(
                    "The PDO connection is already in a transactional state!"
                );
            }
            $result = $pdo->beginTransaction();
            if ($result) {
                $this->transactionLevel = 1;
            }
            return $result;
        }

        $this->transactionLevel += 1;
        return false;
    }

    /**
     * Check if a pdo-transaction has been actually started by this Db instance
     * with the same pdo-connectin at some point and has not been yet committed
     * or rolled-back.
     *
     * This does NOT check the actual transaction status of the pdo connection that could
     * have been initiated autonomally via the pdo instance itself.
     */
    public function inTransaction(): bool
    {
        if ($this->isConnected()) {
            return $this->transactionLevel > 0;
        }

        return false;
    }

    /**
     * Try to commit any pending operations or just go one level up from a nested
     * state.
     *
     * This will also commit any pending operations in transactions initiated via
     * the pdo instance.
     *
     * Returns true if we are at the first transaction nesting level and the commit
     * operation is supported by the pdo-driver in use and if it succeeds.
     */
    public function commit(): bool
    {
        if (!$this->isConnected()) {
            return false;
        }

        if ($this->inRollBack) {
            throw new RuntimeException(
                "Cannot commit: the db connection is in rollBack state!"
            );
        }

        if ($this->transactionLevel === 1) {
            $pdo = $this->pdo();
            $result = $pdo->inTransaction() ? $pdo->commit() : false;
            $this->transactionLevel = 0; // decrement after the commit() call
            return $result;
        }

        if ($this->transactionLevel > 0) {
            $this->transactionLevel -= 1;
        }

        return false;
    }

    /**
     * Try to rollback any pending operations or just set the db connection to
     * a rollback state or go one level up from a nested state.
     *
     * This will also have the effect of discarding any pending operations in
     * transactions initiated via the pdo instance.
     *
     * Returns true if we are at the first transaction nesting level and the rollback
     * operation is supported by the pdo driver in use and if it succeeds.
     */
    public function rollBack(): bool
    {
        if (!$this->isConnected()) {
            return false;
        }

        if ($this->transactionLevel === 1) {
            $this->transactionLevel = 0;
            $this->inRollBack = false;
            return $this->pdo()->rollBack();
        }

        if ($this->transactionLevel > 0) {
            $this->transactionLevel -= 1;
            // mark the whole transaction stack for rollback
            $this->inRollBack = true;
        }

        return false;
    }

    /**
     * @see Sql\DriverInterface::quoteIdentifier()
     */
    public function quoteIdentifier(string $identifier): string
    {
        return ($this->driver ?? $this->getDriver(false))->quoteIdentifier($identifier);
    }

    /**
     * @see Sql\DriverInterface::quoteAlias()
     */
    public function quoteAlias(string $alias): string
    {
        return ($this->driver ?? $this->getDriver(false))->quoteAlias($alias);
    }

    /**
     * @see Sql\DriverInterface::quoteValue()
     *
     * @param mixed $value
     */
    public function quoteValue($value): string
    {
        return ($this->driver ?? $this->getDriver(true))->quoteValue($value);
    }
}
