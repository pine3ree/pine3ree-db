<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace pine3ree\Db\Sql\Statement;

use pine3ree\Db\Exception\InvalidArgumentException;
use pine3ree\Db\Sql;
use pine3ree\Db\Sql\Clause\WhereAwareTrait;
use pine3ree\Db\Sql\Clause\Where;
use pine3ree\Db\Sql\Driver;
use pine3ree\Db\Sql\DriverInterface;
use pine3ree\Db\Sql\Literal;
use pine3ree\Db\Sql\Params;
use pine3ree\Db\Sql\Statement;
use pine3ree\Db\Sql\TableAwareTrait;
use pine3ree\Db\Exception\RuntimeException;

use function gettype;
use function implode;
use function is_array;
use function is_numeric;
use function is_string;
use function sprintf;
use function trim;

/**
 * This class represents an UPDATE sql-statement expression
 *
 * @property-read string|null $table The db table to select from if already set
 * @property-read string|null $quantifier The SELECT quantifier if any
 * @property-read array $set The SET column/value pairs to be updated
 * @property-read Where|null $where The Where clause, built on-first-access if null
 */
class Update extends Statement
{
    use WhereAwareTrait;
    use TableAwareTrait;

    /**
     * @var array Column-value pairs for update
     */
    private $set = [];

    /**
     * @param string $table The db table to update
     */
    public function __construct(string $table = null)
    {
        if (isset($table)) {
            $this->table($table);
        }
    }

    /**
     * Set the db table to update
     *
     * @param string $table
     * @return $this Fluent interface
     */
    public function table(string $table): self
    {
        $this->setTable($table);
        return $this;
    }

    /**
     * Set new value(s) for column(s)
     *
     * @param string|array|mixed[] $column_or_set
     *      A single column or a set of column:value pairs
     * @psalm-param string|array<string, scalar|Literal|null> $column_or_set
     *      A single column or a set of column:value pairs
     * @param mixed $value The value for a single column
     * @return $this Fluent interface
     * @throws InvalidArgumentException
     */
    public function set($column_or_set, $value = null): self
    {
        if (is_string($column_or_set)) {
            $column = trim($column_or_set);
            if (is_numeric($column)) {
                throw new InvalidArgumentException(
                    "A column in an UPDATE statement cannot be numeric!"
                );
            }
            if (empty($column)) {
                throw new InvalidArgumentException(
                    "A column in an UPDATE statement cannot be an empty string!"
                );
            }
            $this->set[$column] = $value;
            return $this;
        }

        if (is_array($column_or_set)) {
            $row = $column_or_set;
            foreach ($row as $column => $value) {
                $this->set($column, $value);
            }
            return $this;
        }

        throw new InvalidArgumentException(sprintf(
            "The set() `\$column_or_set` argument must be either"
            . " a non empty string"
            . " or an array of <string, scalar|Literal|null> pairs,"
            . " `%s` provided!",
            gettype($column_or_set)
        ));
    }

    public function getSQL(DriverInterface $driver = null, Params $params = null): string
    {
        if (isset($this->sql) && $driver === $this->driver && $params === null) {
            return $this->sql;
        }

        $this->driver = $driver; // set last used driver argument
        $this->params = null; // reset previously collected params, if any

        $driver = $driver ?? Driver::ansi();
        $params = $params ?? ($this->params = new Params());

        $base_sql  = $this->getBaseSQL($driver, $params);
        $where_sql = $this->getWhereSQL($driver, $params);
        if (self::isEmptySQL($where_sql)) {
            throw new RuntimeException(
                "UPDATE statements without conditions are not allowed!"
            );
        }

        $this->sql = "{$base_sql} {$where_sql}";
        return $this->sql;
    }

    private function getBaseSQL(DriverInterface $driver, Params $params): string
    {
        if (empty($this->table)) {
            throw new RuntimeException(
                "The UPDATE table has not been defined!"
            );
        }

        if (empty($this->set)) {
            throw new RuntimeException(
                "The UPDATE set clause list has not been defined!"
            );
        }

        $table = $driver->quoteIdentifier($this->table);

        $set = [];
        foreach ($this->set as $column => $value) {
            $column = $driver->quoteIdentifier($column);
            $param  = $this->getValueSQL($params, $value, null, 'set');
            $set[]  = "{$column} = {$param}";
        }

        return Sql::UPDATE . " {$table} " . Sql::SET . " " . implode(", ", $set);
    }

    /**
     * @return mixed
     */
    public function __get(string $name)
    {
        if ('table' === $name) {
            return $this->table;
        }

        if ('set' === $name) {
            return $this->set;
        }

        if ('where' === $name) {
            if (!isset($this->where)) {
                $this->where = new Where();
                $this->where->parent = $this;
            }
            return $this->where;
        }

        return parent::__get($name);
    }

    public function __clone()
    {
        parent::__clone();
        if (isset($this->where)) {
            $this->where = clone $this->where;
            $this->where->parent = $this;
        }
    }
}
