<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Statement;

use P3\Db\Exception\InvalidArgumentException;
use P3\Db\Sql;
use P3\Db\Sql\Clause\WhereAwareTrait;
use P3\Db\Sql\Clause\Where;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Statement;
use P3\Db\Sql\TableAwareTrait;
use P3\Db\Exception\RuntimeException;

use function get_class;
use function gettype;
use function implode;
use function is_array;
use function is_numeric;
use function is_object;
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
     * @return $this
     */
    public function table(string $table): self
    {
        $this->setTable($table);
        return $this;
    }

    /**
     * Set new value(s) for column(s)
     *
     * @param string|array<string: scalar|null|string|Literal> $column_or_row
     *      A single column or a set of column:value pairs
     * @param mixed $value The value for a single column
     * @return $this
     * @throws InvalidArgumentException
     */
    public function set($column_or_row, $value = null): self
    {
        if (is_array($column_or_row)) {
            $row = $column_or_row;
            foreach ($row as $column => $value) {
                if (is_numeric($column)) {
                    throw new InvalidArgumentException(
                        "A column in an UPDATE query cannot be numeric!"
                    );
                }
            }
            $this->set = $row;
            return $this;
        }

        if (is_string($column_or_row)
            && !is_numeric($column_or_row)
        ) {
            $column = trim($column_or_row);
            if (!empty($column)) {
                $this->set[$column] = $value;
            }
            return $this;
        }

        throw new InvalidArgumentException(sprintf(
            "The set() `\$column_or_row` argument must be either a non empty string or an array"
            . " of <column:string> => <value:scalar> pairs, `%s` provided!",
            is_object($column_or_row) ? get_class($column_or_row) : gettype($column_or_row)
        ));
    }

    public function getSQL(Driver $driver = null): string
    {
        if (isset($this->sql)) {
            return $this->sql;
        }

        $this->resetParams();

        $driver = $driver ?? Driver::ansi();

        $base_sql  = $this->getBaseSQL($driver);
        $where_sql = $this->getWhereSQL($driver);
        if (self::isEmptySQL($where_sql)) {
            throw new RuntimeException(
                "UPDATE statements without conditions are not allowed!"
            );
        }

        $this->sql = "{$base_sql} {$where_sql}";
        return $this->sql;
    }

    private function getBaseSQL(Driver $driver): string
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
            $param  = $this->getValueSQL($value, null, 'set');
            $set[]  = "{$column} = {$param}";
        }

        return Sql::UPDATE . " {$table} " . Sql::SET . " " . implode(", ", $set);
    }

    public function __get(string $name)
    {
        if ('table' === $name) {
            return $this->table;
        }
        if ('set' === $name) {
            return $this->set;
        }
        if ('where' === $name) {
            return $this->where ?? $this->where = new Where();
        }

        throw new RuntimeException(
            "Undefined property {$name}!"
        );
    }

    public function __clone()
    {
        parent::__clone();
        if (isset($this->where)) {
            $this->where = clone $this->where;
        }
    }
}
