<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Statement\Traits;

use InvalidArgumentException;
use P3\Db\Sql;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Predicate;
use P3\Db\Sql\PredicateSet;
use RuntimeException;

use function count;
use function current;
use function is_array;
use function key;

/**
 * A trait for sql-statements that can include WHERE, HAVING and ON clauses
 */
trait ConditionAwareTrait
{
    /**
     * Define a clause
     *
     * @param string $property
     * @param string $fqcn A Condition descendant
     * @param string|array|Predicate|PredicateSet $clause
     */
    private function setCondition(string $property, $fqcn, $clause): self
    {
        if (isset($this->{$property})) {
            throw new RuntimeException(
                "Condition of class `{$fqcn}` for property `{$property}` is already set!"
            );
        }

        if (is_array($clause)) {
            // ["&&" => clauses] or ["||" => clauses]
            if (count($clause) === 1
                && isset(PredicateSet::COMB_ID[$comb_id = key($clause)])
                && is_array($clauses = current($clause))
            ) {
                $clause = new $fqcn($comb_id, $clauses);
            } else {
                $clause = new $fqcn(Sql::AND, $clause);
            }
        } elseif (! $clause instanceof $fqcn) {
            $clause = new $fqcn(Sql::AND, $clause);
        }

        $this->{$property} = $clause;
        unset($this->sql, $this->sqls[$property]);

        return $this;
    }

    /**
     * Return the compiled SQL string for a given clause property
     *
     * @param string $property The sql-statement property in which the clause is stored
     * @return string
     */
    private function getConditionSQL(string $property, Driver $driver = null): string
    {
        if (!isset($this->{$property})) {
            return '';
        }

        $condition = $this->{$property};
        if (! $condition instanceof Predicate) {
            throw new InvalidArgumentException(
                "Property {$property} does not hold a Predicate instance!"
            );
        }

        $sql = $condition->getSQL($driver);
        if ($condition->hasParams()) {
            $this->importParams($condition);
        }

        return $sql;
    }
}
