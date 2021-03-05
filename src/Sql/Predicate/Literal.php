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
    /** @var string */
    private $literal;

    public function __construct(string $literal)
    {
        $literal = trim($literal);
        if ('' === $literal) {
            throw new InvalidArgumentException(
                "A SQL-literal expression cannot be empty!"
            );
        }

        $this->literal = $literal;
    }

    public function getSQL(Driver $driver = null): string
    {
        return $this->literal;
    }

    public function __clone()
    {
        // no-op
    }

    public function __get(string $name)
    {
        if ('literal' === $name) {
            return $this->literal;
        };

        throw new RuntimeException(
            "Undefined property {$name}!"
        );
    }
}
