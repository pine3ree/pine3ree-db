<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Clause;

use IteratorAggregate;
use P3\Db\Sql;
use P3\Db\Sql\Clause;
use P3\Db\Sql\Driver;
use P3\Db\Sql\DriverInterface;
use P3\Db\Sql\Params;
use P3\Db\Sql\Predicate;
use P3\Db\Sql\Statement\Select;
use Traversable;

/**
 * This class abstracts the SQL conditional clauses WHERE, HAVING and ON by
 * composing an internal predicate set
 *
 * @property-read Predicate\Set $searchCondition Return the predicate-set of this clause
 */
abstract class ConditionalClause extends Clause implements IteratorAggregate
{
    /**
     * @var Predicate\Set
     */
    protected $searchCondition;

    protected static $useParenthesis = false;

    /**
     * @param null|Predicate[]|Predicate|Predicate\Set|array|string $predicates
     * @param string $defaultLogicalOperator One of `AND`, `OR`,  or `&&`, `||` aliases
     */
    public function __construct($predicates = null, string $defaultLogicalOperator = null)
    {
        $this->searchCondition = new Predicate\Set($predicates, $defaultLogicalOperator);
        $this->searchCondition->parent = $this;
    }

    /**
     * Get the composed predicate-set
     *
     * @return Predicate\Set
     */
    public function getSearchCondition(): Predicate\Set
    {
        return $this->searchCondition;
    }

    /**
     * @see Predicate\Set::isEmpty()
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->searchCondition->isEmpty();
    }

    public function getIterator(): Traversable
    {
        return $this->searchCondition->getIterator();
    }

    public function hasParams(): bool
    {
        return $this->searchCondition->hasParams();
    }

    public function getParams(): ?Params
    {
        return $this->searchCondition->getParams();
    }

    public function getSQL(DriverInterface $driver = null, Params $params = null): string
    {
        if (isset($this->sql) && $params === null) {
            return $this->sql;
        }

        if ($this->searchCondition->isEmpty()) {
            return $this->sql = '';
        }

        $predicates_sql = $this->searchCondition->getSQL($driver ?? Driver::ansi(), $params);
        // @codeCoverageIgnoreStart
        // This case should be already covered by previous isEmpty check
        if ('' === $predicates_sql) {
            return $this->sql = '';
        }
        // @codeCoverageIgnoreEnd

        if (static::$useParenthesis) {
            $predicates_sql = "({$predicates_sql})";
        }

        return $this->sql = "{$this->getName()} {$predicates_sql}";
    }

    /**
     * Add a predicate or a predicate-set to this clause conditions
     *
     * @see Predicate\Set::addPredicate()
     *
     * @param Predicate|string|array $predicate A Predicate|Predicate\Set instance
     *      or a specs-array [identifier, operator, value] or [identifier => value]
     * @return Predicate\Set
     * @throws InvalidArgumentException
     */
    public function addPredicate($predicate): Predicate\Set
    {
        return $this->searchCondition->addPredicate($predicate);
    }

    /**
     * @see Predicate\Set::literal()
     */
    public function literal(string $literal): Predicate\Set
    {
        return $this->searchCondition->literal($literal);
    }

    /**
     * @see Predicate\Set::expression()
     */
    public function expression(string $expression, array $substitutions = []): Predicate\Set
    {
        return $this->searchCondition->expression($expression, $substitutions);
    }

    /**
     * @see Predicate\Set::expr()
     */
    public function expr(string $expression, array $substitutions = []): Predicate\Set
    {
        return $this->searchCondition->expression($expression, $substitutions);
    }

    /**
     * @see Predicate\Set::all()
     */
    public function all($identifier, string $operator, Select $select): Predicate\Set
    {
        return $this->searchCondition->all($identifier, $operator, $select);
    }

    /**
     * @see Predicate\Set::any()
     */
    public function any($identifier, string $operator, Select $select): Predicate\Set
    {
        return $this->searchCondition->any($identifier, $operator, $select);
    }

    /**
     * @see Predicate\Set::some()
     */
    public function some($identifier, string $operator, Select $select): Predicate\Set
    {
        return $this->searchCondition->some($identifier, $operator, $select);
    }

    /**
     * @see Predicate\Set::between()
     */
    public function between($identifier, $min_value, $max_value): Predicate\Set
    {
        return $this->searchCondition->between($identifier, $min_value, $max_value);
    }

    /**
     * @see Predicate\Set::notBetween()
     */
    public function notBetween($identifier, $min_value, $max_value): Predicate\Set
    {
        return $this->searchCondition->notBetween($identifier, $min_value, $max_value);
    }

    /**
     * @see Predicate\Set::exists()
     */
    public function exists(Select $select): Predicate\Set
    {
        return $this->searchCondition->exists($select);
    }

    /**
     * @see Predicate\Set::notExists()
     */
    public function notExists(Select $select): Predicate\Set
    {
        return $this->searchCondition->notExists($select);
    }

    /**
     * @see Predicate\Set::in()
     */
    public function in($identifier, array $value_list): Predicate\Set
    {
        return $this->searchCondition->in($identifier, $value_list);
    }

    /**
     * @see Predicate\Set::notIn()
     */
    public function notIn($identifier, array $value_list): Predicate\Set
    {
        return $this->searchCondition->notIn($identifier, $value_list);
    }

    /**
     * @see Predicate\Set::is()
     */
    public function is($identifier, $value): Predicate\Set
    {
        return $this->searchCondition->is($identifier, $value);
    }

    /**
     * @see Predicate\Set::isNot()
     */
    public function isNot($identifier, $value): Predicate\Set
    {
        return $this->searchCondition->isNot($identifier, $value);
    }

    /**
     * @see Predicate\Set::isNull()
     */
    public function isNull($identifier): Predicate\Set
    {
        return $this->searchCondition->isNull($identifier);
    }

    /**
     * @see Predicate\Set::isNotNull()
     */
    public function isNotNull($identifier): Predicate\Set
    {
        return $this->searchCondition->isNotNull($identifier);
    }

    /**
     * @see Predicate\Set::isTrue()
     */
    public function isTrue($identifier): Predicate\Set
    {
        return $this->searchCondition->isTrue($identifier);
    }

    /**
     * @see Predicate\Set::isFalse()
     */
    public function isFalse($identifier): Predicate\Set
    {
        return $this->searchCondition->isFalse($identifier);
    }

    /**
     * @see Predicate\Set::isUnknown()
     */
    public function isUnknown($identifier): Predicate\Set
    {
        return $this->searchCondition->isUnknown($identifier);
    }

    /**
     * @see Predicate\Set::isNotUnknown()
     */
    public function isNotUnknown($identifier): Predicate\Set
    {
        return $this->searchCondition->isNotUnknown($identifier);
    }

    /**
     * @see Predicate\Set::like()
     */
    public function like($identifier, $value): Predicate\Set
    {
        return $this->searchCondition->like($identifier, $value);
    }

    /**
     * @see Predicate\Set::notLike()
     */
    public function notLike($identifier, $value): Predicate\Set
    {
        return $this->searchCondition->notLike($identifier, $value);
    }

    /**
     * @see Predicate\Set::equal()
     */
    public function equal($identifier, $value): Predicate\Set
    {
        return $this->searchCondition->equal($identifier, $value);
    }

    /**
     * @see Predicate\Set::eq()
     */
    public function eq($identifier, $value): Predicate\Set
    {
        return $this->searchCondition->eq($identifier, $value);
    }

    /**
     * @see Predicate\Set::notEqual()
     */
    public function notEqual($identifier, $value): Predicate\Set
    {
        return $this->searchCondition->notEqual($identifier, $value);
    }

    /**
     * @see Predicate\Set::neq()
     */
    public function neq($identifier, $value): Predicate\Set
    {
        return $this->searchCondition->neq($identifier, $value);
    }

    /**
     * @see Predicate\Set::ne()
     */
    public function ne($identifier, $value): Predicate\Set
    {
        return $this->searchCondition->ne($identifier, $value);
    }

    /**
     * @see Predicate\Set::lessThan()
     */
    public function lessThan($identifier, $value): Predicate\Set
    {
        return $this->searchCondition->lessThan($identifier, $value);
    }

    /**
     * @see Predicate\Set::lt()
     */
    public function lt($identifier, $value): Predicate\Set
    {
        return $this->searchCondition->lt($identifier, $value);
    }

    /**
     * @see Predicate\Set::lessThanEqual()
     */
    public function lessThanEqual($identifier, $value): Predicate\Set
    {
        return $this->searchCondition->lessThanEqual($identifier, $value);
    }

    /**
     * @see Predicate\Set::lte()
     */
    public function lte($identifier, $value): Predicate\Set
    {
        return $this->searchCondition->lte($identifier, $value);
    }

    /**
     * @see Predicate\Set::greaterThanEqual()
     */
    public function greaterThanEqual($identifier, $value): Predicate\Set
    {
        return $this->searchCondition->greaterThanEqual($identifier, $value);
    }

    /**
     * @see Predicate\Set::gte()
     */
    public function gte($identifier, $value): Predicate\Set
    {
        return $this->searchCondition->gte($identifier, $value);
    }

    /**
     * @see Predicate\Set::greaterThan()
     */
    public function greaterThan($identifier, $value): Predicate\Set
    {
        return $this->searchCondition->greaterThan($identifier, $value);
    }

    /**
     * @see Predicate\Set::gt()
     */
    public function gt($identifier, $value): Predicate\Set
    {
        return $this->searchCondition->gt($identifier, $value);
    }

    /**
     * @see Predicate\Set::and()
     */
    public function and(): Predicate\Set
    {
        return $this->searchCondition->and();
    }

    /**
     * @see Predicate\Set::and()
     */
    public function or(): Predicate\Set
    {
        return $this->searchCondition->or();
    }

    /**
     * Open a nested predicate-set which will translate into a SQL group of
     * conditions inside parenthesis
     *
     * @see Predicate\Set::notRegExp()
     *
     * @param string $defaultLogicalOperator The default logical operator for the nested set
     *
     * @return Predicate\Set
     */
    public function openGroup(string $defaultLogicalOperator = Sql::AND): Predicate\Set
    {
        return $this->searchCondition->openGroup($defaultLogicalOperator);
    }

    /**
     * Provide access to private properties
     *
     * @param string $name
     */
    public function __get(string $name)
    {
        if ('searchCondition' === $name) {
            return $this->searchCondition;
        };

        return parent::__get($name);
    }

    public function __clone()
    {
        parent::__clone();
        $this->searchCondition = clone $this->searchCondition;
        $this->searchCondition->parent = $this;
    }
}
