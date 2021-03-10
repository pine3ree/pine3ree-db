<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use P3\Db\Sql\Element;

/**
 * A sql-statement object's goal is to abstract a sql statement collecting parts
 * (abtractions for clauses, predicates, ...) later compiled by a sql-driver into
 * a platform pecific SQL-string
 */
abstract class Statement extends Element
{
    /**
     * @var string[] Cached parts of the final sql statement
     */
    protected $sqls = [];

    /**
     * @var int custom index counter
     */
    protected static $index = 0;

    protected static function assertValidValue($value, string $type = '')
    {
        parent::assertValidValue($value, "{$type}statement ");
    }

    /**
     * Remove cached SQL string for the given part - or all parts f no part is
     * provided - and also the full cached SQL string itself
     */
    protected function clearPartialSQL(string $part = null)
    {
        parent::clearSQL();
        if (isset($part)) {
            unset($this->sqls[$part]);
        } else {
            $this->sqls = [];
        }
    }
}
