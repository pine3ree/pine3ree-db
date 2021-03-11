<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Clause;

use Closure;
use P3\Db\Sql\Clause\Where;
use P3\Db\Sql\Clause\ConditionalClauseAwareTrait;
use P3\Db\Sql\DriverInterface;
use P3\Db\Sql\Predicate;

/**
 * A trait for sql-statements that can include WHERE clauses
 */
trait WhereAwareTrait
{
    use ConditionalClauseAwareTrait;

    /** @var Where|null */
    protected $where;

    /**
     * Set WHERE conditions
     *
     * @param string|array|Predicate|Closure|Where $where
     * @return $this
     */
    public function where($where): self
    {
        if ($where instanceof Closure) {
            $where($this->where ?? $this->where = new Where());
            $this->sql = null;
            return $this;
        }

        $this->setConditionalClause('where', Where::class, $where);
        return $this;
    }

    private function getWhereSQL(DriverInterface $driver): string
    {
        return $this->getConditionalClauseSQL('where', $driver);
    }
}
