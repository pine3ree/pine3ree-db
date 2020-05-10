<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use InvalidArgumentException;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Element;

use function get_class;
use function gettype;
use function is_object;
use function is_scalar;
use function is_string;
use function sprintf;

/**
 * Predicate represents a single SQL condition that can be evaluates by the underlying
 * database software. It is usually part of set of conditions (PredicateSet) such
 * as the WHERE, the HAVING and the ON clauses or their nested sets of conditions.
 */
abstract class Predicate extends Element
{
    protected static function assertValidIdentifier($identifier)
    {
        if (!is_string($identifier) && ! $identifier instanceof Literal) {
            throw new InvalidArgumentException(sprintf(
                "A predicate identifier must be either a string or an SQL Literal,"
                . " '%s' provided in class `%s`!!",
                is_object($identifier) ? get_class($identifier) : gettype($identifier),
                static::class
            ));
        }
    }

    protected static function assertValidValue($value)
    {
        $is_valid = is_scalar($value) || null === $value || $value instanceof Literal;
        if (!$is_valid) {
            throw new InvalidArgumentException(sprintf(
                "A predicate value must be either a scalar or an Sql Literal"
                . " expression instance, `%s` provided in class``%s!",
                is_object($value) ? get_class($value) : gettype($value),
                static::class
            ));
        }
    }
}
