<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Statement;

use RuntimeException;
use P3\Db\Sql\Clause\Where;
use P3\Db\Sql\Statement\DML;
use P3\Db\Sql\Statement\Traits\ClauseAwareTrait;

/**
 * This class represents an UPDATE sql-statement expression
 *
 * @property-read Where|null $where The Where clause if any
 */
class Update extends DML
{
    use ClauseAwareTrait;

    /**
     * @var array Column-value pairs for update
     */
    private $set;

    /** @var Where|null */
    protected $where;

    public function __construct($table = null, string $alias = null)
    {
        if (!empty($table)) {
            parent::setTable($table, $alias);
        }
    }

    /**
     * Set the db table to update
     *
     * @param string|array $table
     * @return $this
     */
    public function table($table, string $alias = null): self
    {
        parent::setTable($table, $alias);
        return $this;
    }

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
        } elseif (is_string($columnOrRow)) {
            $column = trim($columnOrRow);
            if ($column) {
                $this->set[$column] = $value;
            }
        }

        return $this;
    }

    public function getSQL(): string
    {
        if (isset($this->sql)) {
            return $this->sql;
        }

        $sqls = [];

        $sqls[] = $this->getBaseSQL();

        $where_sql = $this->getWhereSQL();
        if ($this->isEmptySQL($where_sql)) {
            throw new RuntimeException(
                "UPDATE queries without conditions are not allowed!"
            );
        }

        $sqls[] = $where_sql;

        $this->sql = implode(" ", $sqls);
        return $this->sql;
    }

    protected function getBaseSQL(): string
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

        $table = $this->quoteIdentifier($this->table);
        if (!empty($this->alias) && $alias = $this->quoteAlias($this->alias)) {
            $table .= " {$alias}";
        }

        $set = [];
        foreach ($this->set as $column => $value) {
            $column = $this->quoteIdentifier($column);
            $marker = $this->createNamedParam($value);
            $set[] = "{$column} = {$marker}";
        }

        return "UPDATE {$table} SET " . implode(", ", $set);
    }

    /** @var string|array|Predicate|Where| */
    public function where($where): self
    {
        return $this->setClause('where', Where::class, $where);
    }

    private function getWhereSQL(bool $stripParentheses = false): string
    {
        return $this->getClauseSQL('where', $stripParentheses);
    }

    public function __get(string $name)
    {
        if ('where' === $name) {
            return $this->where;
        };
//        if ('having' === $having) {
//            return $this->having;
//        };
//        if ('on' === $name) {
//            return $this->on;
//        };
    }
}
