<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use P3\Db\Sql\Element;

/**
 * Predicate represents a single SQL condition that can be evaluates by the underlying
 * database software. It is usually part of set of conditions (Predicate\Set) such
 * as the WHERE, the HAVING and the ON clauses or their nested sets of conditions.
 */
abstract class Predicate extends Element
{
    protected static function assertValidIdentifier($identifier, string $type = '')
    {
        parent::assertValidIdentifier($identifier, "{$type}predicate ");
    }

    protected static function assertValidValue($value, string $type = '')
    {
        parent::assertValidValue($value, "{$type}predicate ");
    }
}
