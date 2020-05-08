<?php

/**
 * @package     p3-db
 * 
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Statement\Traits;

use InvalidArgumentException;
use RuntimeException;

/**
 * Trait for statements that are related to a main database table
 */
trait TableAwareTrait
{
    /**
     * @var string The database table name
     */
    protected $table;

    /**
     * @var string The database table alias
     */
    protected $alias;

    /**
     * Validate and set the query table/alias
     *
     * @param string $table
     * @param string|null $alias
     * @return void
     */
    protected function setTable(string $table, string $alias = null): void
    {
        if (isset($this->table)) {
            throw new RuntimeException(
                "Cannot change db-table name for this query, table already set to `{$this->table}`!"
            );
        }

        if (empty($table)) {
            throw new InvalidArgumentException(
                "The db-table name argument cannot be empty!"
            );
        }

        $this->table = $table;
        if (!empty($alias)) {
            $this->alias = $alias;
        }
    }

    /**
     * Prepend the dml-statement primary-table alias if not already present
     *
     * @param string $column
     * @return string
     */
    public function normalizeColumn(string $column): string
    {
        $column = str_replace([$this->ql, $this->qr] , '', $column); // unquote the column first
        if (false === strpos($column, '.')) {
            return $this->alias ? "{$this->alias}.{$column}" : $column;
        }

        return $column;
    }
}
