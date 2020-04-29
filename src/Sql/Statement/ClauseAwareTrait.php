<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Statement;

use P3\Db\Sql;
use P3\Db\Sql\Clause;
use P3\Db\Sql\Clause\Having;
use P3\Db\Sql\Clause\On;
use P3\Db\Sql\Clause\Where;
use P3\Db\Sql\Expression;
use P3\Db\Sql\Predicate;
use P3\Db\Sql\PredicateSet;
use P3\Db\Sql\Statement;

/**
 * Trait ClauseAwareTrait
 */
trait ClauseAwareTrait
{
    /** @var Where|null */
    protected $where;

    /** @var Having|null */
    protected $having;

    /** @var On|null */
    protected $on;

    /** @var string|array|Predicate|Where| */
    public function where($where): self
    {
        return $this->setClause('where', Where::class, $where);
    }

    protected function getWhereSQL(): string
    {
        return $this->getClauseSQL('where');

        if (!isset($this->where)) {
            return '';
        }

        $sql = $this->where->getSQL(true);
        if ($this instanceof Statement) {
            $this->importParams($this->where);
        }

        return $sql;
    }

    /**
     *
     * @param string $property
     * @param string $fqcn
     * @param string|array|Predicate|PredicateSet $clause
     */
    private function setClause($property, $fqcn, $clause): self
    {
        if (isset($this->{$property})) {
            throw new \RuntimeException(
                "Clause of class `{$fqcn}` for property `{$property}` is already set!"
            );
        }

        if (is_array($clause)) {
            if (count($clause) === 1
                && isset(PredicateSet::COMB_ID[$comb_id = key($clause)])
            ) {
                $clause = new $fqcn($comb_id, current($clause));
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

    protected function getClauseSQL(string $property): string
    {
        if (!isset($this->{$property})) {
            return '';
        }

        $sql = $this->{$property}->getSQL(true);
        if ($this instanceof Statement) {
            $this->importParams($this->{$property});
        }

        return $sql;
    }



    /**
     * Import parameters and types from inner predicate
     *
     * @param Predicate $predicate
     */
    private function importParams(Clause $clause)
    {
        foreach ($clause->getParams() as $index => $value) {
            $this->params[$index] = $value;
        }
        foreach ($clause->getParamsTypes() as $index => $type) {
            $this->params_types[$index] = $type;
        }
    }
}
