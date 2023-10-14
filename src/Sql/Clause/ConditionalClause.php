<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\Db\Sql\Clause;

use IteratorAggregate;
use pine3ree\Db\Exception\InvalidArgumentException;
use pine3ree\Db\Sql;
use pine3ree\Db\Sql\Alias;
use pine3ree\Db\Sql\Clause;
use pine3ree\Db\Sql\DriverInterface;
use pine3ree\Db\Sql\Identifier;
use pine3ree\Db\Sql\Literal;
use pine3ree\Db\Sql\Params;
use pine3ree\Db\Sql\Predicate;
use pine3ree\Db\Sql\Statement\Select;
use Traversable;

/**
 * This class abstracts the SQL conditional clauses WHERE, HAVING and ON by
 * composing an internal predicate set
 *
 * @property-read Predicate\Set $searchCondition Return the predicate-set of this clause
 */
abstract class ConditionalClause extends Clause implements IteratorAggregate
{
    protected Predicate\Set $searchCondition;

    protected static bool $useParenthesis = false;

    /**
     * @param null|Predicate[]|Predicate|Predicate\Set|array|string $predicates
     * @param string $defaultLogicalOperator One of `AND`, `OR`,  or `&&`, `||` aliases
     */
    public function __construct($predicates = null, string $defaultLogicalOperator = null)
    {
        $this->searchCondition = new Predicate\Set($predicates, $defaultLogicalOperator);
        $this->searchCondition->setParent($this);
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
        if ($this->searchCondition->isEmpty()) {
            return $this->sql = '';
        }

        if ($this->hasValidSqlCache($driver, $params)) {
            return $this->sql;
        }

        $this->driver = $driver; // Set last used driver argument

        $predicates_sql = $this->searchCondition->getSQL($driver, $params);
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
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param string $operator
     * @param Select $select
     * @return Predicate\Set This clause's composed search-condition
     */
    public function all($identifier, string $operator, Select $select): Predicate\Set
    {
        return $this->searchCondition->all($identifier, $operator, $select);
    }

    /**
     * @see Predicate\Set::any()
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param string $operator
     * @param Select $select
     * @return Predicate\Set This clause's composed search-condition
     */
    public function any($identifier, string $operator, Select $select): Predicate\Set
    {
        return $this->searchCondition->any($identifier, $operator, $select);
    }

    /**
     * @see Predicate\Set::some()
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param string $operator
     * @param Select $select
     * @return Predicate\Set This clause's composed search-condition
     */
    public function some($identifier, string $operator, Select $select): Predicate\Set
    {
        return $this->searchCondition->some($identifier, $operator, $select);
    }

    /**
     * @see Predicate\Set::between()
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param scalar|Literal $min_value
     * @param scalar|Literal $max_value
     * @return Predicate\Set This clause's composed search-condition
     */
    public function between($identifier, $min_value, $max_value): Predicate\Set
    {
        return $this->searchCondition->between($identifier, $min_value, $max_value);
    }

    /**
     * @see Predicate\Set::notBetween()
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param scalar|Literal $min_value
     * @param scalar|Literal $max_value
     * @return Predicate\Set This clause's composed search-condition
     */
    public function notBetween($identifier, $min_value, $max_value): Predicate\Set
    {
        return $this->searchCondition->notBetween($identifier, $min_value, $max_value);
    }

    /**
     * @see Predicate\Set::exists()
     *
     * @param Select $select
     * @return Predicate\Set This clause's composed search-condition
     */
    public function exists(Select $select): Predicate\Set
    {
        return $this->searchCondition->exists($select);
    }

    /**
     * @see Predicate\Set::notExists()
     *
     * @param Select $select
     * @return Predicate\Set This clause's composed search-condition
     */
    public function notExists(Select $select): Predicate\Set
    {
        return $this->searchCondition->notExists($select);
    }

    /**
     * @see Predicate\Set::in()
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param array|Select $valueList
     * @return Predicate\Set This clause's composed search-condition
     */
    public function in($identifier, $valueList): Predicate\Set
    {
        return $this->searchCondition->in($identifier, $valueList);
    }

    /**
     * @see Predicate\Set::notIn()
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param array|Select $valueList
     * @return Predicate\Set This clause's composed search-condition
     */
    public function notIn($identifier, $valueList): Predicate\Set
    {
        return $this->searchCondition->notIn($identifier, $valueList);
    }

    /**
     * @see Predicate\Set::is()
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param bool|null|string $value
     * @return Predicate\Set This clause's composed search-condition
     */
    public function is($identifier, $value): Predicate\Set
    {
        return $this->searchCondition->is($identifier, $value);
    }

    /**
     * @see Predicate\Set::isNot()
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param bool|null|string $value
     * @return Predicate\Set This clause's composed search-condition
     */
    public function isNot($identifier, $value): Predicate\Set
    {
        return $this->searchCondition->isNot($identifier, $value);
    }

    /**
     * @see Predicate\Set::isNull()
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @return Predicate\Set This clause's composed search-condition
     */
    public function isNull($identifier): Predicate\Set
    {
        return $this->searchCondition->isNull($identifier);
    }

    /**
     * @see Predicate\Set::isNotNull()
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @return Predicate\Set This clause's composed search-condition
     */
    public function isNotNull($identifier): Predicate\Set
    {
        return $this->searchCondition->isNotNull($identifier);
    }

    /**
     * @see Predicate\Set::isTrue()
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @return Predicate\Set This clause's composed search-condition
     */
    public function isTrue($identifier): Predicate\Set
    {
        return $this->searchCondition->isTrue($identifier);
    }

    /**
     * @see Predicate\Set::isFalse()
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @return Predicate\Set This clause's composed search-condition
     */
    public function isFalse($identifier): Predicate\Set
    {
        return $this->searchCondition->isFalse($identifier);
    }

    /**
     * @see Predicate\Set::isUnknown()
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @return Predicate\Set This clause's composed search-condition
     */
    public function isUnknown($identifier): Predicate\Set
    {
        return $this->searchCondition->isUnknown($identifier);
    }

    /**
     * @see Predicate\Set::isNotUnknown()
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @return Predicate\Set This clause's composed search-condition
     */
    public function isNotUnknown($identifier): Predicate\Set
    {
        return $this->searchCondition->isNotUnknown($identifier);
    }

    /**
     * @see Predicate\Set::like()
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param string|Literal $pattern
     * @param string|null $escape
     * @return Predicate\Set This clause's composed search-condition
     */
    public function like($identifier, $pattern, string $escape = null): Predicate\Set
    {
        return $this->searchCondition->like($identifier, $pattern, $escape);
    }

    /**
     * @see Predicate\Set::notLike()
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param string|Literal $pattern
     * @param string|null $escape
     * @return Predicate\Set This clause's composed search-condition
     */
    public function notLike($identifier, $pattern, string $escape = null): Predicate\Set
    {
        return $this->searchCondition->notLike($identifier, $pattern, $escape);
    }

    /**
     * @see Predicate\Set::equal()
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param scalar|Literal|Identifier|Alias|null $value
     * @return Predicate\Set This clause's composed search-condition
     */
    public function equal($identifier, $value): Predicate\Set
    {
        return $this->searchCondition->equal($identifier, $value);
    }

    /**
     * @see Predicate\Set::eq()
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param scalar|Literal|Identifier|Alias|null $value
     * @return Predicate\Set This clause's composed search-condition
     */
    public function eq($identifier, $value): Predicate\Set
    {
        return $this->searchCondition->eq($identifier, $value);
    }

    /**
     * @see Predicate\Set::notEqual()
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param scalar|Literal|Identifier|Alias|null $value
     * @return Predicate\Set This clause's composed search-condition
     */
    public function notEqual($identifier, $value): Predicate\Set
    {
        return $this->searchCondition->notEqual($identifier, $value);
    }

    /**
     * @see Predicate\Set::neq()
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param scalar|Literal|Identifier|Alias|null $value
     * @return Predicate\Set This clause's composed search-condition
     */
    public function neq($identifier, $value): Predicate\Set
    {
        return $this->searchCondition->neq($identifier, $value);
    }

    /**
     * @see Predicate\Set::ne()
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param scalar|Literal|Identifier|Alias|null $value
     * @return Predicate\Set This clause's composed search-condition
     */
    public function ne($identifier, $value): Predicate\Set
    {
        return $this->searchCondition->ne($identifier, $value);
    }

    /**
     * @see Predicate\Set::lessThan()
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param scalar|Literal|Identifier|Alias|null $value
     * @return Predicate\Set This clause's composed search-condition
     */
    public function lessThan($identifier, $value): Predicate\Set
    {
        return $this->searchCondition->lessThan($identifier, $value);
    }

    /**
     * @see Predicate\Set::lt()
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param scalar|Literal|Identifier|Alias|null $value
     * @return Predicate\Set This clause's composed search-condition
     */
    public function lt($identifier, $value): Predicate\Set
    {
        return $this->searchCondition->lt($identifier, $value);
    }

    /**
     * @see Predicate\Set::lessThanEqual()
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param scalar|Literal|Identifier|Alias|null $value
     * @return Predicate\Set This clause's composed search-condition
     */
    public function lessThanEqual($identifier, $value): Predicate\Set
    {
        return $this->searchCondition->lessThanEqual($identifier, $value);
    }

    /**
     * @see Predicate\Set::lte()
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param scalar|Literal|Identifier|Alias|null $value
     * @return Predicate\Set This clause's composed search-condition
     */
    public function lte($identifier, $value): Predicate\Set
    {
        return $this->searchCondition->lte($identifier, $value);
    }

    /**
     * @see Predicate\Set::greaterThanEqual()
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param scalar|Literal|Identifier|Alias|null $value
     * @return Predicate\Set This clause's composed search-condition
     */
    public function greaterThanEqual($identifier, $value): Predicate\Set
    {
        return $this->searchCondition->greaterThanEqual($identifier, $value);
    }

    /**
     * @see Predicate\Set::gte()
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param scalar|Literal|Identifier|Alias|null $value
     * @return Predicate\Set This clause's composed search-condition
     */
    public function gte($identifier, $value): Predicate\Set
    {
        return $this->searchCondition->gte($identifier, $value);
    }

    /**
     * @see Predicate\Set::greaterThan()
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param scalar|Literal|Identifier|Alias|null $value
     * @return Predicate\Set This clause's composed search-condition
     */
    public function greaterThan($identifier, $value): Predicate\Set
    {
        return $this->searchCondition->greaterThan($identifier, $value);
    }

    /**
     * @see Predicate\Set::gt()
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param scalar|Literal|Identifier|Alias|null $value
     * @return Predicate\Set This clause's composed search-condition
     */
    public function gt($identifier, $value): Predicate\Set
    {
        return $this->searchCondition->gt($identifier, $value);
    }

    /**
     * @see Predicate\Set::and()
     * @return Predicate\Set This clause's composed search-condition
     */
    public function and(): Predicate\Set
    {
        return $this->searchCondition->and();
    }

    /**
     * @see Predicate\Set::or()
     * @return Predicate\Set This clause's composed search-condition
     */
    public function or(): Predicate\Set
    {
        return $this->searchCondition->or();
    }

    /**
     * Open a nested predicate-set which will translate into a SQL group of
     * conditions inside parenthesis
     *
     * @see Predicate\Set::beginGroup()
     *
     * @param string $defaultLogicalOperator The default logical operator for the nested set
     *
     * @return Predicate\Set
     */
    public function beginGroup(string $defaultLogicalOperator = Sql::AND): Predicate\Set
    {
        return $this->searchCondition->beginGroup($defaultLogicalOperator);
    }

    /**
     * @return mixed
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
        $this->searchCondition->setParent($this);
    }
}
