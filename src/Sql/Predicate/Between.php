<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use InvalidArgumentException;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Predicate;

use function count;

/**
 * This class represents a sql BETWEEN condition
 */
class Between extends Predicate
{
    protected $identifier;
    protected $min_value;
    protected $max_value;
    protected $not = false;

    /**
     * @param string|Literal $identifier
     * @param array $limits
     */
    public function __construct($identifier, array $limits)
    {
        self::assertValidIdentifier($identifier);
        $this->identifier = $identifier;

        self::assertValidLimits($limits);

        $min_value = $limits[0];
        $max_value = $limits[1];

        self::assertValidValue($min_value);
        self::assertValidValue($max_value);

        $this->min_value = $min_value;
        $this->max_value = $max_value;
    }

    private static function assertValidLimits(array $limits)
    {
        $count = count($limits);
        if ($count !== 2) {
            throw new InvalidArgumentException(
                "A BETWEEN limits pair must be either a 2-valued array, {$count}-valued array provided!"
            );
        }
    }

    public function getSQL(Driver $driver = null): string
    {
        if (isset($this->sql)) {
            return $this->sql;
        }

        $driver = $driver ?? Driver::ansi();

        $identifier = $this->identifier instanceof Literal
            ? $this->identifier->getSQL()
            : $driver->quoteIdentifier($this->identifier);

        $operator = ($this->not ? "NOT " : "") . "BETWEEN";

        $min = $this->min_value instanceof Literal
            ? $this->min_value->getSQL()
            : $this->createNamedParam($this->min_value);

        $max = $this->max_value instanceof Literal
            ? $this->max_value->getSQL()
            : $this->createNamedParam($this->max_value);

        return $this->sql = "{$identifier} {$operator} {$min} AND {$max}";
    }
}
