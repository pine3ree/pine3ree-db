<?php

/**
 * @package     p3-db
 * 
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Statement;

use InvalidArgumentException;
use RuntimeException;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Condition\Where;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Predicate;
use P3\Db\Sql\Statement;
use P3\Db\Sql\Statement\Traits\ConditionAwareTrait;
use P3\Db\Sql\Statement\Traits\TableAwareTrait;

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
    use ConditionAwareTrait;
    use TableAwareTrait;

    /**
     * @var array Column-value pairs for update
     */
    private $set = [];

    /** @var Where|null */
    protected $where;

    /**
     * @param string $table The db table to update
     */
    public function __construct(string $table = null)
    {
        if (!empty($table)) {
            $this->table($table);
        }
    }

    /**
     * Set the db table to update
     *
     * @param string|array $table
     * @return $this
     */
    public function table($table): self
    {
        $this->setTable($table);
        return $this;
    }

    /**
     *
     * @param string|array<string: mixed> $columnOrRow A single column or a set of column:value pairs
     * @param mixed $value The value for a single column
     * @return $this
     * @throws InvalidArgumentException
     */
    public function set($columnOrRow, $value = null): self
    {
        if (is_array($columnOrRow)) {
            $row = $columnOrRow;
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

        if (is_string($columnOrRow)) {
            $column = trim($columnOrRow);
            if ($column) {
                $this->set[$column] = $value;
            }
            return $this;
        }

        throw new InvalidArgumentException(sprintf(
            "The set() columnOrRow argument muste be either a string or an array"
            . " of column:value pairs, `%s` provided!",
            is_object($columnOrRow) ? get_class($columnOrRow) : gettype($columnOrRow)
        ));
    }

    public function getSQL(Driver $driver = null): string
    {
        if (isset($this->sql)) {
            return $this->sql;
        }

        $driver = $driver ?? Driver::ansi();

        $base_sql  = $this->getBaseSQL($driver);
        $where_sql = $this->getWhereSQL($driver);

        if ($this->isEmptySQL($where_sql)) {
            throw new RuntimeException(
                "UPDATE queries without conditions are not allowed!"
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
        if (!empty($this->alias) && $alias = $driver->quoteAlias($this->alias)) {
            $table .= " {$alias}";
        }

        $set = [];
        foreach ($this->set as $column => $value) {
            $column = $driver->quoteIdentifier($column);
            $param  = $value instanceof Literal
                ? $val->getSQL()
                : $this->createNamedParam($value);
            $set[] = "{$column} = {$param}";
        }

        return "UPDATE {$table} SET " . implode(", ", $set);
    }

    /**
     * Add WHERE conditions
     *
     * @param string|array|Predicate|Where $where
     * @return $this
     */
    public function where($where): self
    {
        $this->setCondition('where', Where::class, $where);
        return $this;
    }

    private function getWhereSQL(Driver $driver): string
    {
        return $this->getConditionSQL('where', $driver);
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
    }
}
