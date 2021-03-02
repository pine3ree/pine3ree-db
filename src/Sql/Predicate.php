<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use InvalidArgumentException;
use P3\Db\Sql\Alias;
use P3\Db\Sql\Element;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Identifier;
use P3\Db\Sql\Literal;

use function get_class;
use function gettype;
use function is_object;
use function sprintf;

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
    protected function getValueSQL(
        $value,
        int $param_type = null,
        string $name = null,
        Driver $driver = null
    ): string {
        if ($value instanceof Literal) {
            return $value->getSQL();
        }

        if ($value instanceof Identifier || $value instanceof Alias) {
            return $value->getSQL($driver);
        }

        return $this->createParam($value, $param_type, $name);
    }

    protected static function assertValidIdentifier($identifier, string $type = '')
    {
        parent::assertValidIdentifier($identifier, "{$type}predicate ");
    }

    protected static function assertValidValue($value, string $type = '')
    {
        if (is_scalar($value)
            || null === $value
            || $value instanceof Literal
            || $value instanceof Identifier
            || $value instanceof Alias
        ) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            "A {$type}predicate value must be either"
            . " a scalar, "
            . " null,"
            . " a SQL-literal,"
            . " a SQL-alias or"
            . " a SQL-identifier,"
            . " `%s` provided in class``%s!",
            is_object($value) ? get_class($value) : gettype($value),
            static::class
        ));
    }
}
