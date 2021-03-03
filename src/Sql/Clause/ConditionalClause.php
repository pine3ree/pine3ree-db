<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Clause;

use P3\Db\Sql;
use P3\Db\Sql\Clause;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Predicate;

/**
 * This class abstracts the SQL conditional clauses WHERE, HAVING and ON
 *
 * @property-read Predicate\Set $conditions Return the predicate-set of this clause
 */
abstract class ConditionalClause extends Clause
{
    /**
     * @var Predicate\Set
     */
    protected $conditions;

    protected static $useParenthesis = false;

    /**
     * @param null|Predicate[]|self|Predicate|Predicate\Set|array|string $predicates
     * @param string $defaultLogicalOperator One of `AND`, `OR`,  or `&&`, `||` aliases
     */
    public function __construct($predicates = null, string $defaultLogicalOperator = null)
    {
        $this->conditions = new Predicate\Set($predicates, $defaultLogicalOperator);
    }

    /**
     * @see Predicate\Set::isEmpty()
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->conditions->isEmpty();
    }

    public function getParams(): array
    {
        return $this->conditions->getParams();
    }

    public function getParamsTypes(bool $return_pdo_const_names = false): array
    {
        return $this->conditions->getParamsTypes($return_pdo_const_names);
    }

    public function getSQL(Driver $driver = null): string
    {
        if (isset($this->sql)) {
            return $this->sql;
        }

        // No need to reset the parameters here, this is forwarded to the composed
        // predicate-set

        $predicates_sql = $this->conditions->getSQL($driver ?? Driver::ansi());
        if (Sql::isEmptySQL($predicates_sql)) {
            return $this->sql = '';
        }

        if (static::$useParenthesis) {
            $predicates_sql = "({$predicates_sql})";
        }

        $this->sql = "{$this->getName()} {$predicates_sql}";
        return $this->sql;
    }

    /**
     * Add a predicate or a predicate-set to this clause conditions
     *
     * @see Predicate\Set::addPredicate()
     *
     * @param Predicate|string|array $predicate A Predicate|Predicate\Set instance
     *      or a specs-array [identifier, operator, value] or [identifier => value]
     * @throws InvalidArgumentException
     * @return $this Provides fluent interface
     */
    public function addPredicate($predicate): self
    {
        $this->sql = null;
        $this->conditions->addPredicate($predicate);
        return $this;
    }

    /**
     * @see Predicate\Set::literal()
     */
    public function literal(string $literal): Predicate\Set
    {
        $this->sql = null;
        return $this->conditions->literal($literal);
    }

    /**
     * @see Predicate\Set::expression()
     */
    public function expression(string $expression, array $substitutions = []): Predicate\Set
    {
        $this->sql = null;
        return $this->conditions->expression($expression, $substitutions);
    }

    /**
     * @see Predicate\Set::expr()
     */
    public function expr(string $expression, array $substitutions = []): Predicate\Set
    {
        $this->sql = null;
        return $this->conditions->expression($expression, $substitutions);
    }

    /**
     * @see Predicate\Set::all()
     */
    public function all($identifier, string $operator, Select $select): Predicate\Set
    {
        $this->sql = null;
        return $this->conditions->all($identifier, $operator, $select);
    }

    /**
     * @see Predicate\Set::any()
     */
    public function any($identifier, string $operator, Select $select): Predicate\Set
    {
        $this->sql = null;
        return $this->conditions->any($identifier, $operator, $select);
    }

    /**
     * @see Predicate\Set::some()
     */
    public function some($identifier, string $operator, Select $select): Predicate\Set
    {
        $this->sql = null;
        return $this->conditions->some($identifier, $operator, $select);
    }

    /**
     * @see Predicate\Set::between()
     */
    public function between($identifier, $min_value, $max_value): Predicate\Set
    {
        $this->sql = null;
        return $this->conditions->between($identifier, $min_value, $max_value);
    }

    /**
     * @see Predicate\Set::notBetween()
     */
    public function notBetween($identifier, $min_value, $max_value): Predicate\Set
    {
        $this->sql = null;
        return $this->conditions->notBetween($identifier, $min_value, $max_value);
    }

    /**
     * @see Predicate\Set::exists()
     */
    public function exists(Select $select): Predicate\Set
    {
        $this->sql = null;
        return $this->conditions->exists($select);
    }

    /**
     * @see Predicate\Set::notExists()
     */
    public function notExists(Select $select): Predicate\Set
    {
        $this->sql = null;
        return $this->conditions->notExists($select);
    }

    /**
     * @see Predicate\Set::in()
     */
    public function in($identifier, array $value_list): Predicate\Set
    {
        $this->sql = null;
        return $this->conditions->in($identifier, $value_list);
    }

    /**
     * @see Predicate\Set::notIn()
     */
    public function notIn($identifier, array $value_list): Predicate\Set
    {
        $this->sql = null;
        return $this->conditions->notIn($identifier, $value_list);
    }

    /**
     * @see Predicate\Set::is()
     */
    public function is($identifier, $value): Predicate\Set
    {
        $this->sql = null;
        return $this->conditions->is($identifier, $value);
    }

    /**
     * @see Predicate\Set::isNot()
     */
    public function isNot($identifier, $value): Predicate\Set
    {
        $this->sql = null;
        return $this->conditions->isNot($identifier, $value);
    }

    /**
     * @see Predicate\Set::isNull()
     */
    public function isNull($identifier): Predicate\Set
    {
        $this->sql = null;
        return $this->conditions->isNull($identifier);
    }

    /**
     * @see Predicate\Set::isNotNull()
     */
    public function isNotNull($identifier): Predicate\Set
    {
        $this->sql = null;
        return $this->conditions->isNotNull($identifier);
    }

    /**
     * @see Predicate\Set::isTrue()
     */
    public function isTrue($identifier): Predicate\Set
    {
        $this->sql = null;
        return $this->conditions->isTrue($identifier);
    }

    /**
     * @see Predicate\Set::isFalse()
     */
    public function isFalse($identifier): Predicate\Set
    {
        $this->sql = null;
        return $this->conditions->isFalse($identifier);
    }

    /**
     * @see Predicate\Set::isUnknown()
     */
    public function isUnknown($identifier): Predicate\Set
    {
        $this->sql = null;
        return $this->conditions->isUnknown($identifier);
    }

    /**
     * @see Predicate\Set::isNotUnknown()
     */
    public function isNotUnknown($identifier): Predicate\Set
    {
        $this->sql = null;
        return $this->conditions->isNotUnknown($identifier);
    }

    /**
     * @see Predicate\Set::like()
     */
    public function like($identifier, $value): Predicate\Set
    {
        $this->sql = null;
        return $this->conditions->like($identifier, $value);
    }

    /**
     * @see Predicate\Set::notLike()
     */
    public function notLike($identifier, $value): Predicate\Set
    {
        $this->sql = null;
        return $this->conditions->notLike($identifier, $value);
    }

    /**
     * @see Predicate\Set::equal()
     */
    public function equal($identifier, $value): Predicate\Set
    {
        $this->sql = null;
        return $this->conditions->equal($identifier, $value);
    }

    /**
     * @see Predicate\Set::eq()
     */
    public function eq($identifier, $value): Predicate\Set
    {
        $this->sql = null;
        return $this->conditions->eq($identifier, $value);
    }

    /**
     * @see Predicate\Set::notEqual()
     */
    public function notEqual($identifier, $value): Predicate\Set
    {
        $this->sql = null;
        return $this->conditions->notEqual($identifier, $value);
    }

    /**
     * @see Predicate\Set::neq()
     */
    public function neq($identifier, $value): Predicate\Set
    {
        $this->sql = null;
        return $this->conditions->neq($identifier, $value);
    }

    /**
     * @see Predicate\Set::ne()
     */
    public function ne($identifier, $value): Predicate\Set
    {
        $this->sql = null;
        return $this->conditions->ne($identifier, $value);
    }

    /**
     * @see Predicate\Set::lessThan()
     */
    public function lessThan($identifier, $value): Predicate\Set
    {
        $this->sql = null;
        return $this->conditions->lessThan($identifier, $value);
    }

    /**
     * @see Predicate\Set::lt()
     */
    public function lt($identifier, $value): Predicate\Set
    {
        $this->sql = null;
        return $this->conditions->lt($identifier, $value);
    }

    /**
     * @see Predicate\Set::lessThanEqual()
     */
    public function lessThanEqual($identifier, $value): Predicate\Set
    {
        $this->sql = null;
        return $this->conditions->lessThanEqual($identifier, $value);
    }

    /**
     * @see Predicate\Set::lte()
     */
    public function lte($identifier, $value): Predicate\Set
    {
        $this->sql = null;
        return $this->conditions->lte($identifier, $value);
    }

    /**
     * @see Predicate\Set::greaterThanEqual()
     */
    public function greaterThanEqual($identifier, $value): Predicate\Set
    {
        $this->sql = null;
        return $this->conditions->greaterThanEqual($identifier, $value);
    }

    /**
     * @see Predicate\Set::gte()
     */
    public function gte($identifier, $value): Predicate\Set
    {
        $this->sql = null;
        return $this->conditions->gte($identifier, $value);
    }

    /**
     * @see Predicate\Set::greaterThan()
     */
    public function greaterThan($identifier, $value): Predicate\Set
    {
        $this->sql = null;
        return $this->conditions->greaterThan($identifier, $value);
    }

    /**
     * @see Predicate\Set::gt()
     */
    public function gt($identifier, $value): Predicate\Set
    {
        $this->sql = null;
        return $this->conditions->gt($identifier, $value);
    }

    /**
     * @see Predicate\Set::regExp()
     */
    public function regExp($identifier, array $regexp, bool $case_sensitive = false): Predicate\Set
    {
        $this->sql = null;
        return $this->conditions->regExp($identifier, $regexp, $case_sensitive);
    }

    /**
     * @see Predicate\Set::notRegExp()
     */
    public function notRegExp($identifier, array $regexp, bool $case_sensitive = false): Predicate\Set
    {
        $this->sql = null;
        return $this->conditions->notRegExp($identifier, $regexp, $case_sensitive);
    }

    /**
     * @see Predicate\Set::and()
     */
    public function and(): Predicate\Set
    {
        return $this->conditions->and();
    }

    /**
     * @see Predicate\Set::and()
     */
    public function or(): Predicate\Set
    {
        return $this->conditions->or();
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
    public function open(string $defaultLogicalOperator = Sql::AND): Predicate\Set
    {
        $this->sql = null;
        return $this->conditions->open($defaultLogicalOperator);
    }

    /**
     * Provide access to private properties
     *
     * @param string $name
     */
    public function __get(string $name)
    {
        if ('conditions' === $name) {
            return $this->conditions;
        };
    }

    public function __clone()
    {
        parent::__clone();
        $this->conditions = clone $this->conditions;
    }
}
