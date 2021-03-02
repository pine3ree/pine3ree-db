<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use InvalidArgumentException;
use P3\Db\Sql;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Predicate;

/**
 * This class represents a sql BETWEEN condition
 */
class Between extends Predicate
{
    /** @var string|Literal */
    protected $identifier;

    /** @var mixed */
    protected $min_value;

    /** @var mixed */
    protected $max_value;

    protected static $not = false;

    /**
     * @var int custom index counter
     */
    protected static $index = 0;

    /**
     * @param string|Literal $identifier
     * @param mixed $min_value
     * @param mixed $max_value
     */
    public function __construct($identifier, $min_value, $max_value)
    {
        self::assertValidIdentifier($identifier);
        $this->identifier = $identifier;

        self::assertValidLimit($min_value);
        self::assertValidLimit($max_value);

        $this->min_value = $min_value;
        $this->max_value = $max_value;
    }

    protected static function assertValidLimit($value)
    {
        $is_valid = is_scalar($value) || $value instanceof Literal;
        if (!$is_valid) {
            throw new InvalidArgumentException(sprintf(
                "A BETWEEN predicate value must be either a scalar or an Sql Literal"
                . " expression instance, `%s` provided in class``%s!",
                is_object($value) ? get_class($value) : gettype($value),
                static::class
            ));
        }
    }

    public function getSQL(Driver $driver = null): string
    {
        if (isset($this->sql)) {
            return $this->sql;
        }

        $this->resetParams();

        $driver = $driver ?? Driver::ansi();

        $identifier = $this->quoteGenericIdentifier($this->identifier, $driver);
        $operator = static::$not ? Sql::NOT_BETWEEN : Sql::BETWEEN;
        $min = $this->createSqlForValue($this->min_value, null, 'min');
        $max = $this->createSqlForValue($this->max_value, null, 'max');

        return $this->sql = "{$identifier} {$operator} {$min} AND {$max}";
    }
}
