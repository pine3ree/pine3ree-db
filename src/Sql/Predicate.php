<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use P3\Db\Sql\Element;
use P3\Db\Sql\Literal;

/**
 * Predicate represents a single SQL condition that can be evaluates by the underlying
 * database software. It is usually part of set of conditions (Predicate\Set) such
 * as the WHERE, the HAVING and the ON clauses or their nested sets of conditions.
 */
abstract class Predicate extends Element
{
    /**
     * Create a SQL representation (either actual string or marker) for a given value
     *
     * @param mixed $value
     * @param int|null $param_type Optional PDO::PARAM_* constant
     * @param string|null $name Optional parameter name seed for pdo marker generation
     * @return string
     */
    protected function getValueSQL($value, int $param_type = null, string $name = null): string
    {
        return $value instanceof Literal
            ? $value->getSQL()
            : $this->createParam($value, $param_type, $name);
    }

    protected static function assertValidIdentifier($identifier, string $type = '')
    {
        parent::assertValidIdentifier($identifier, $type ?: 'predicate ');
    }

    protected static function assertValidValue($value, string $type = '')
    {
        parent::assertValidValue($value, $type ?: 'predicate ');
    }
}
