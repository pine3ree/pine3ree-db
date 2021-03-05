<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use InvalidArgumentException;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Predicate;
use RuntimeException;

use function trim;

/**
 * This class represents a sql literal-expression predicate
 *
 * @property-read string $literal The literal string itself
 */
class Literal extends Predicate
{
    public function __construct(string $literal)
    {
        $literal = trim($literal);
        if ('' === $literal) {
            throw new InvalidArgumentException(
                "A SQL-literal expression cannot be empty!"
            );
        }

        $this->sql = $literal;
    }

    public function getSQL(Driver $driver = null): string
    {
        return $this->sql;
    }

    public function __clone()
    {
        // no-op
    }

    /**
     * Do not clear literals sql cache.
     * There is no compilation involved must sql must be always set
     */
    protected function clearSQL()
    {
        // no-op
    }

    public function __get(string $name)
    {
        if ('literal' === $name) {
            return $this->sql;
        };

        throw new RuntimeException(
            "Undefined property {$name}!"
        );
    }
}
