<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\Db\Sql;

use pine3ree\Db\Sql\Element;

/**
 * Abstracts a sql statement collecting parts (abstractions for clauses, predicates, ...)
 * later compiled by a sql-driver into a platform specific SQL-string
 */
abstract class Statement extends Element
{
    /**
     * @var string[] Cached parameter-less parts of the final sql statement
     */
    protected $sqls = [];

    protected static function assertValidValue($value, string $type = ''): void
    {
        parent::assertValidValue($value, "{$type}statement ");
    }

    /**
     * Remove cached SQL string for the given part - or all parts f no part is
     * provided - and also the full cached SQL string itself
     *
     * @param string $part The partial cache identifier
     * @return void
     */
    protected function clearPartialSQL(string $part = null): void
    {
        $this->clearSQL();
        if (isset($part)) {
            unset($this->sqls[$part]);
        } else {
            $this->sqls = [];
        }
    }
}
