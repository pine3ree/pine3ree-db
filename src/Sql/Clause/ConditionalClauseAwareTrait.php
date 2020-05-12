<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Clause;

use InvalidArgumentException;
use P3\Db\Sql;
use P3\Db\Sql\Clause\ConditionalClause;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Predicate;
use P3\Db\Sql\Predicate\Set as PredicateSet;
use RuntimeException;

use function count;
use function current;
use function is_array;
use function key;

/**
 * A trait for sql-statements that can include WHERE, HAVING and ON clauses
 */
trait ConditionalClauseAwareTrait
{
    /**
     * Define a clause
     *
     * @param string $property
     * @param string $fqcn A Condition descendant
     * @param string|array|Predicate|PredicateSet|ConditionalClause $condition
     */
    private function setConditionalClause(string $property, $fqcn, $condition): self
    {
        if (isset($this->{$property})) {
            throw new RuntimeException(
                "Conditional clause of class `{$fqcn}` for property `{$property}` is already set!"
            );
        }

        if (is_array($condition)) {
            // ["&&" => conditions] or ["||" => conditions]
            if (count($condition) === 1
                && isset(PredicateSet::COMB_ID[$comb_id = key($condition)])
                && is_array($conditions = current($condition))
            ) {
                $clause = new $fqcn($comb_id, $conditions);
            } else {
                $clause = new $fqcn(Sql::AND, $condition);
            }
        } elseif ($condition instanceof $fqcn) {
            $clause = $condition;
        } else {
            $clause = new $fqcn(Sql::AND, $condition);
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
    private function getConditionalClauseSQL(string $property, Driver $driver = null): string
    {
        if (!isset($this->{$property})) {
            return '';
        }

        $condition = $this->{$property};
        if (! $condition instanceof ConditionalClause) {
            throw new InvalidArgumentException(
                "Property {$property} does not hold a ConditionalClause instance!"
            );
        }

        $sql = $condition->getSQL($driver);
        $this->importParams($condition);

        return $sql;
    }
}
