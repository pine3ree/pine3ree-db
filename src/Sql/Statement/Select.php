<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Statement;

use P3\Db\Sql\Clause\Having;
use P3\Db\Sql\Clause\On;
use P3\Db\Sql\Clause\Where;
use P3\Db\Sql\Statement\DML;
use P3\Db\Sql\Statement\Traits\ClauseAwareTrait;

/**
 * This class represents a SELECT sql-statement expression
 *
 * @property-read Where|null $where The Where clause if any
 * @property-read Having|null $where The Having clause if any
 * @property-read On|null $where The On clause if any
 */
class Select extends DML
{
    use ClauseAwareTrait;

    /** @var Where|null */
    protected $where;

    /** @var Having|null */
    protected $having;

    /** @var On|null */
    protected $on;

    public function getSQL(bool $stripClausesParentheses = false): string
    {

    }

    public function getBaseSQL(bool $stripClausesParentheses = false): string
    {

    }

    /** @var string|array|Predicate|Where| */
    public function where($where): self
    {
        return $this->setClause('where', Where::class, $where);
    }

    protected function getWhereSQL(bool $stripParentheses = false): string
    {
        return $this->getClauseSQL('where', $stripParentheses);
    }

    /** @var string|array|Predicate|Having| */
    public function having($having): self
    {
        return $this->setClause('having', Having::class, $having);
    }

    protected function getHavingSQL(bool $stripParentheses = false): string
    {
        return $this->getClauseSQL('having', $stripParentheses);
    }

    /** @var string|array|Predicate|Having| */
    public function on($on): self
    {
        return $this->setClause('on', On::class, $on);
    }

    protected function getOnSQL(bool $stripParentheses = false): string
    {
        return $this->getClauseSQL('on', $stripParentheses);
    }

    public function __get(string $name)
    {
        if ('where' === $name) {
            return $this->where;
        };
        if ('having' === $having) {
            return $this->having;
        };
        if ('on' === $name) {
            return $this->on;
        };
    }
}
