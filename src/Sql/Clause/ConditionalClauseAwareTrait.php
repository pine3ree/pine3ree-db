<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Clause;

use P3\Db\Sql;
use P3\Db\Sql\Clause\ConditionalClause;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Predicate;
use RuntimeException;

use function count;
use function current;
use function is_array;
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
        if (is_array($clause)
            // ["&&" => conditions] or ["||" => conditions]
            && count($clause) === 1
            && (null !== $logicalOp = Predicate\Set::COMB_ID[key($clause)] ?? null)
            && is_array($conditions = current($clause))
        ) {
            $clause = new $fqcn($conditions, $logicalOp);
        } elseif (! $clause instanceof $fqcn) {
            $clause = new $fqcn($clause, Sql::AND);
        }

        $this->{$property} = $clause;

        $this->sql = null;

        return $this;
    }

    /**
     * Return the compiled SQL string for a given clause property
     *
     * @param string $property The sql-statement property in which the clause is stored
     * @return string
     */
    private function getConditionalClauseSQL(string $property, Driver $driver = null): string
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

        $sql = $clause->getSQL($driver);
        if ('' === $sql = trim($sql)) {
            return '';
        }

        $this->importParams($clause);

        return $sql;
    }
}
