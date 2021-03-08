<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use P3\Db\Exception\InvalidArgumentException;
use P3\Db\Sql;
use P3\Db\Sql\Element;

use function gettype;
use function is_string;
use function sprintf;

/**
 * Predicate represents a single SQL condition that can be evaluates by the underlying
 * database software. It is usually part of set of conditions (Predicate\Set) such
 * as the WHERE, the HAVING and the ON clauses or their nested sets of conditions.
 */
abstract class Predicate extends Element
{
    protected static function assertValidIdentifier(&$identifier, string $type = '')
    {
        parent::assertValidIdentifier($identifier, "{$type}predicate ");
    }

    protected static function assertValidOperator($operator)
    {
        if (!is_string($operator)
            || !Sql::isSupportedOperator($operator)
        ) {
            throw new InvalidArgumentException(sprintf(
                "Invalid or unsupported SQL operator, '%s' provided!",
                is_string($operator) ? $operator : gettype($operator)
            ));
        }
    }

    protected static function assertValidValue($value, string $type = '')
    {
        parent::assertValidValue($value, "{$type}predicate ");
    }
}
