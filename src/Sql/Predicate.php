<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use InvalidArgumentException;
use P3\Db\Sql\Expression;

/**
 * Predicate represents a single SQL condition that can be evaluates by the underlying
 * database software. It is usually part of set of conditions (PredicateSet) such
 * as the WHERE, the HAVING and the ON clauses or their nested sets of conditions.
 */
abstract class Predicate extends Expression
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
        $is_valid = is_scalar($value) || null === value || $value instanceof Literal;
        if (!$is_valid) {
            throw new InvalidArgumentException(sprintf(
                "A predicte value must be either a scalar or an Sql Literal"
                . " expression instance, `%s` provided in class``%s!",
                is_object($value) ? get_class($value) : gettype($value),
                static::class
            ));
        }
    }
}
