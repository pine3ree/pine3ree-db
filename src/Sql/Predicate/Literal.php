<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use InvalidArgumentException;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Predicate;

/**
 * This class represents a sql literal-expression predicate
 */
class Literal extends Predicate
{
    public function __construct(string $literal)
    {
        $sql = trim($literal);
        if ('' === $sql) {
            throw new InvalidArgumentException(
                "A SQL Literal predicate cannot be empty!"
            );
        }
        $this->sql = $sql;
    }

    public function getSQL(Driver $driver = null): string
    {
        return $this->sql;
    }
}
