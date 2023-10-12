<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\Db\Sql\Clause;

use pine3ree\Db\Exception\InvalidArgumentException;
use pine3ree\Db\Sql;
use pine3ree\Db\Sql\Clause\ConditionalClause;
use pine3ree\Db\Sql\DriverInterface;
use pine3ree\Db\Sql\Element;
use pine3ree\Db\Sql\Params;
use pine3ree\Db\Sql\Predicate;
use pine3ree\Db\Exception\RuntimeException;

use function count;
use function current;
use function is_array;
use function is_subclass_of;
use function key;
use function trim;

/**
 * A trait for sql-statements that can include WHERE, HAVING and ON clauses
 */
trait ConditionalClauseAwareTrait
{
    /**
     * Set a conditional clause into a consumer class property
     *
     * @param string $property
     * @param string $fqcn A ConditionalClause descendant class
     * @param ConditionalClause|Predicate\Set|Predicate|array|string $clause
     */
    private function setConditionalClause(string $property, $fqcn, $clause): self
    {
        self::assertValidConditionaClauseClass($fqcn);

        if (is_array($clause)
            // ["&&" => conditions] or ["||" => conditions]
            && count($clause) === 1
            && (null !== $logicalOp = Predicate\Set::COMB_ID[key($clause)] ?? null)
            && is_array($conditions = current($clause))
        ) {
            $clause = new $fqcn($conditions, $logicalOp);
        } elseif ($clause instanceof $fqcn) {
            $parent = $clause->getParent();
            if ($parent !== null && $parent !== $this) {
                $clause = clone $clause;
            }
        } else {
            $clause = new $fqcn($clause, Sql::AND);
        }

        $this->{$property} = $clause;
        if ($this instanceof Element) {
            $clause->setParent($this);
            if (!$clause->isEmpty()) {
                $this->clearSQL();
            }
        }

        return $this;
    }

    /**
     * Return the compiled SQL string for a given clause property
     *
     * @param string $property The sql-statement property in which the clause is stored
     * @param DriverInterface $driver
     * @return string
     * @throws RuntimeException
     */
    private function getConditionalClauseSQL(string $property, DriverInterface $driver, Params $params): string
    {
        if (!isset($this->{$property})) {
            return '';
        }

        $clause = $this->{$property};
        if (! $clause instanceof ConditionalClause) {
            throw new RuntimeException(
                "Property {$property} does not hold a ConditionalClause instance!"
            );
        }

        $sql = $clause->getSQL($driver, $params);
        if ('' === $sql = trim($sql)) {
            return '';
        }

        return $sql;
    }

    /**
     * @param string $fqcn
     *
     * @throws InvalidArgumentException
     */
    private static function assertValidConditionaClauseClass(string $fqcn): void
    {
        if (!is_subclass_of($fqcn, ConditionalClause::class, true)) {
            throw new InvalidArgumentException(
                "The provided class `{$fqcn}` is not extends"
                . " `" . ConditionalClause::class . "`!"
            );
        }
    }
}
