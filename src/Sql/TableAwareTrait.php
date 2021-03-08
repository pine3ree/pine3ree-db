<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use P3\Db\Exception\InvalidArgumentException;
use P3\Db\Exception\RuntimeException;

use function trim;

/**
 * Trait for statements that always operate on a target database table
 */
trait TableAwareTrait
{
    /**
     * @var string The database table name
     */
    private $table;

    /**
     * Validate and set the query table/alias
     *
     * @param string $table
     * @return void
     */
    protected function setTable(string $table): void
    {
        if (isset($this->table)) {
            throw new RuntimeException(
                "Cannot change the element's table, it was already already set to `{$this->table}`!"
            );
        }

        $table = trim($table);
        if ('' === $table) {
            throw new InvalidArgumentException(
                "A database table name cannot be an empty string!"
            );
        }

        $this->table = $table;
    }
}
