<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use P3\Db\Sql\Literal;
use P3\Db\Sql\Predicate;

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
     * @param scalar|literal|array<int, scalar|literal> $min_value
     * @param scalar|literal $max_value
     */
    public function __construct($identifier, $min_value, $max_value = null)
    {
        self::assertValidIdentifier($identifier);

        $this->identifier = $identifier;

        if (2 === func_num_args() && is_array($min_value)) {
            $max_value = $min_value[1] ?? null;
            $min_value = $min_value[0];
        }

        self::assertValidValue($max_value);
        self::assertValidValue($min_value);

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

    private static function assertValidValue($value)
    {
        $is_valid = is_scalar($value) || is_null($value) || $value instanceof Literal;
        if (!$is_valid) {
            throw new InvalidArgumentException(sprintf(
                "BETWEEN min/max values must be either a scalar or an Sql Literal"
                . " expression instance, `%s` provided!",
                is_object($value) ? get_class($value) : gettype($value)
            ));
        }
    }

    public function getSQL(): string
    {
        if (isset($this->sql)) {
            return $this->sql;
        }

        $identifier = $this->identifier instanceof Literal
            ? (string)$this->identifier
            : $this->quoteIdentifier($this->identifier);

        $operator = ($this->not ? "NOT " : "") . "BETWEEN";

        $min = $this->min_value instanceof Literal
            ? (string)$this->min_value
            : $this->createNamedParam($this->min_value);

        $max = $this->max_value instanceof Literal
            ? (string)$this->max_value
            : $this->createNamedParam($this->max_value);

        return $this->sql =  "{$identifier} {$operator} {$min} AND {$max}";
    }
}
