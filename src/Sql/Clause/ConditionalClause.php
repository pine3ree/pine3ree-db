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
 * @method $this methodName(type $paramName) Proxy to Predicate\Set::
 */
abstract class ConditionalClause extends Clause
{
    /**
     * @var Predicate\Set
     */
    protected $conditions;

    /**
     * @param string $combined_by One of `AND`, `OR`, `&&`, `||`
     * @param null|Predicate[]|self|Predicate|Predicate\Set|array|string $predicates
     */
    public function __construct(string $combined_by = null, $predicates = null)
    {
        $this->conditions = new Predicate\Set($combined_by, $predicates);
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

        $predicates_sql = $this->conditions->getSQL($driver ?? Driver::ansi());
        if (Sql::isEmptySQL($predicates_sql)) {
            return $this->sql = '';
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
    public function addPredicate($predicate)
    {
        $this->conditions->addPredicate($predicate);
        return $this;
    }

    public function literal(string $literal): self
    {
        $this->conditions->literal($literal);
        return $this;
    }

    public function expression(string $expression, array $params = []): self
    {
        $this->conditions->expression($expression, $params);
        return $this;
    }

    public function all($identifier, string $operator, Select $select): self
    {
        $this->conditions->all($identifier, $operator, $select);
        return $this;
    }

    public function any($identifier, string $operator, Select $select): self
    {
        $this->conditions->any($identifier, $operator, $select);
        return $this;
    }

    public function some($identifier, string $operator, Select $select): self
    {
        $this->conditions->some($identifier, $operator, $select);
        return $this;
    }

    public function between($identifier, $min_value, $max_value): self
    {
        $this->conditions->between($identifier, $min_value, $max_value);
        return $this;
    }

    public function notBetween($identifier, $min_value, $max_value): self
    {
        $this->conditions->notBetween($identifier, $min_value, $max_value);
        return $this;
    }

    public function exists(Select $select): self
    {
        $this->conditions->exists($select);
        return $this;
    }

    public function notExists(Select $select): self
    {
        $this->conditions->notExists($select);
        return $this;
    }

    public function in($identifier, array $value_list): self
    {
        $this->conditions->in($identifier, $value_list);
        return $this;
    }

    public function notIn($identifier, array $value_list): self
    {
        $this->conditions->notIn($identifier, $value_list);
        return $this;
    }

    public function isNull($identifier): self
    {
         $this->conditions->isNull($identifier);
        return $this;
    }

    public function isNotNull($identifier): self
    {
        $this->conditions->isNotNull($identifier);
        return $this;
    }

    public function like($identifier, $value): self
    {
        $this->conditions->like($identifier, $value);
        return $this;
    }

    public function notLike($identifier, $value): self
    {
        $this->conditions->notLike($identifier, $value);
        return $this;
    }

    public function equal($identifier, $value): self
    {
        $this->conditions->equal($identifier, Sql::EQUAL, $value);
        return $this;
    }

    public function notEqual($identifier, $value): self
    {
        $this->conditions->notEqual($identifier, $value);
        return $this;
    }

    public function lessThan($identifier, $value): self
    {
        $this->conditions->lessThan($identifier, $value);
        return $this;
    }

    public function lessThanEqual($identifier, $value): self
    {
        $this->conditions->lessThanEqual($identifier, $value);
        return $this;
    }

    public function greaterThanEqual($identifier, $value): self
    {
        $this->conditions->greaterThanEqual($identifier, $value);
        return $this;
    }

    public function greaterThan($identifier, $value): self
    {
        $this->conditions->greaterThan($identifier, $value);
        return $this;
    }

    public function regExp($identifier, array $regexp, bool $case_sensitive = false): self
    {
        return $this->conditions->addPredicate(
            new Predicate\RegExp($identifier, $regexp, $case_sensitive)
        );
    }

    public function notRegExp($identifier, array $regexp, bool $case_sensitive = false): self
    {
        return $this->conditions->addPredicate(
            new Predicate\NotRegExp($identifier, $regexp, $case_sensitive)
        );
    }
}
