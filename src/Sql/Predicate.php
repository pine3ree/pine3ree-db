<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\Db\Sql;

use pine3ree\Db\Exception\InvalidArgumentException;
use pine3ree\Db\Sql;
use pine3ree\Db\Sql\Element;

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
    /**
     * @param mixed $identifier
     * @param string $type
     * @return void
     */
    protected static function assertValidIdentifier(&$identifier, string $type = '')
    {
        parent::assertValidIdentifier($identifier, "{$type}predicate ");
    }

    /**
     * @param mixed $operator
     * @return void
     */
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
