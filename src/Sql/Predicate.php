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
 * Predicate represents a single SQL condition that can be evaluated by the underlying
 * database software. It is usually part of set of conditions (Predicate\Set) such
 * as the WHERE, the HAVING and the ON clauses or their nested sets of conditions.
 */
abstract class Predicate extends Element
{

    /**
     * Utility short-named method to move the context to, i.e. return, the parent
     * element, if any, or return NULL
     *
     * @return ElementInterface|null Provides fluent interface
     */
    public function up(): ?ElementInterface
    {
        return $this->parent;
    }

    /**
     * Utility short-named method to move the context to, i.e. return,the top-level
     * element in the parent chain if any parent at all, or return NULL
     *
     * @return ElementInterface|null Provides fluent interface
     */
    public function top(): ?ElementInterface
    {
        $parent = $this->parent;
        while ($parent) {
            $top = $parent;
            $parent = $parent->parent;
        }

        return $top ?? null;
    }

    /**
     * @param mixed $identifier
     * @param string $type
     */
    protected static function assertValidIdentifier(&$identifier, string $type = ''): void
    {
        parent::assertValidIdentifier($identifier, "{$type}predicate ");
    }

    /**
     * @param mixed $operator
     */
    protected static function assertValidOperator($operator): void
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

    protected static function assertValidValue($value, string $type = ''): void
    {
        parent::assertValidValue($value, "{$type}predicate ");
    }
}
