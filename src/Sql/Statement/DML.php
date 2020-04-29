<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Statement;

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
     * @return $this
     */
    protected function setTable($table, string $alias = null): self
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

        return $this;
    }
}
