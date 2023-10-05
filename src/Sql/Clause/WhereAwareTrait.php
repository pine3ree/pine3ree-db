<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace pine3ree\Db\Sql\Clause;

use Closure;
use pine3ree\Db\Sql\Clause\Where;
use pine3ree\Db\Sql\Clause\ConditionalClauseAwareTrait;
use pine3ree\Db\Sql\DriverInterface;
use pine3ree\Db\Sql\Params;
use pine3ree\Db\Sql\Predicate;

/**
 * A trait for sql-statements that can include WHERE clauses
 */
trait WhereAwareTrait
{
    use ConditionalClauseAwareTrait;

    protected ?Where $where = null;

    /**
     * Set WHERE conditions
     *
     * @param string|array|Predicate|Closure|Where $where
     * @return $this Fluent interface
     */
    public function where($where): self
    {
        if ($where instanceof Closure) {
            if (!isset($this->where)) {
                $this->where = new Where();
                $this->where->parent = $this;
            }
            $where($this->where);
            return $this;
        }

        $this->setConditionalClause('where', Where::class, $where);
        return $this;
    }

    private function getWhereSQL(DriverInterface $driver, Params $params): string
    {
        return $this->getConditionalClauseSQL('where', $driver, $params);
    }
}
