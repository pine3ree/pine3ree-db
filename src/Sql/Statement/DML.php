<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Statement;

use RuntimeException;
use P3\Db\Sql\Statement;

/**
 * Class Statement
 */
abstract class DML extends Statement
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
     * @var string|null The database table primary-key if any
     */
    protected $pk;

    /**
     * Validate and set the query table/alias
     *
     * @param array|string $table
     * @param string|null $alias
     * @return void
     */
    protected function setTable($table, string $alias = null): void
    {
        if (isset($this->table)) {
            throw new RuntimeException(
                "Cannot change db-table name for this query, table already set to `{$this->table}`!"
            );
        }

        if (is_array($table)) {
            $key = key($table);
            $table = current($table);
            if (!is_numeric($key) && $key !== '') {
                $this->alias = $key;
            }
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
        $column = trim($column, $this->qi); // unquote the column first
        if (false === strpos($column, '.')) {
            return $this->alias ? "{$this->alias}.{$column}" : $column;
        }

        return $column;
    }
}
