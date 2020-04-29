<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Statement;

use P3\Db\Sql\Statement\DML;
use P3\Db\Sql\Statement\ClauseAwareTrait;

/**
 * This class represent an UPDATE SQL statement
 *
 * @property-read Where|null $where The WHere clause if any
 */
class Update extends DML
{
    use ClauseAwareTrait;

    /**
     * @var array Column-value pairs for update
     */
    private $set;

    public function __construct($table = null, string $alias = null)
    {
        if (!empty($table)) {
            parent::setTable($table, $alias);
        }
    }

    public function table($table, string $alias = null): self
    {
        return parent::setTable($table, $alias);
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
        if (!$this->isEmptySQL($where_sql)) {
            $sqls[] = $where_sql;
        } else {
            return ''; // inhibit condition-less update
        }

        $this->sql = implode(" ", $sqls);
        return $this->sql;
    }

    protected function getBaseSQL(): string
    {
        if (empty($this->table) || empty($this->set)) {
            return '';
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

    public function __get(string $name)
    {
        if ('where' === $name) {
            return $this->where;
        };
    }
}
