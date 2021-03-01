<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use InvalidArgumentException;
use P3\Db\Sql\Alias;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Element;
use P3\Db\Sql\Literal;

use function get_class;
use function gettype;
use function is_object;
use function is_scalar;
use function is_string;
use function sprintf;

/**
 * Predicate represents a single SQL condition that can be evaluates by the underlying
 * database software. It is usually part of set of conditions (Predicate\Set) such
 * as the WHERE, the HAVING and the ON clauses or their nested sets of conditions.
 */
abstract class Predicate extends Element
{
    /**
     * Quote the left part of a predicate based on its type
     *
     * @param string|Alias|Literal $identifier
     * @param Driver $driver A SQL-driver
     * @return string
     * @throws InvalidArgumentException
     */
    protected function quoteIdentifier($identifier, Driver $driver): string
    {
        // the identifier is considered a db table column, quote accordingly
        if (is_string($identifier)) {
            return $driver->quoteIdentifier($this->identifier);
        }

        // The indentifier is specified to be a SQL-alias, quote accordingly
        if ($identifier instanceof Alias) {
            $driver->quoteAlias($this->identifier->getSQL($driver));
        }

        // the identifier is generic SQL-literal, so no quoting
        if ($identifier instanceof Literal) {
            return $this->identifier->getSQL();
        }

        throw new InvalidArgumentException(sprintf(
            "Invalid predicate identifier type, must be either a string, a"
            . " SQL-alias or a SQL-literal, '%s' provided in class `%s`!",
            is_object($identifier) ? get_class($identifier) : gettype($identifier),
            static::class
        ));
    }

    protected static function assertValidIdentifier($identifier)
    {
        if (!is_string($identifier)
            && ! $identifier instanceof Alias
            && ! $identifier instanceof Literal
        ) {
            throw new InvalidArgumentException(sprintf(
                "A predicate identifier must be either a string, a SQL-alias or a SQL-literal,"
                . " '%s' provided in class `%s`!",
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
                "A predicate value must be either a scalar, null or an Sql Literal"
                . " expression instance, `%s` provided in class``%s!",
                is_object($value) ? get_class($value) : gettype($value),
                static::class
            ));
        }
    }
}
