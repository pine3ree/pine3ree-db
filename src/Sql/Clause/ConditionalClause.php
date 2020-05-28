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
        $this->conditions->addPredicate(
            new Predicate\Literal($literal)
        );

        return $this;
    }

    public function expression(string $expression, array $params = []): self
    {
        $this->conditions->addPredicate(
            new Predicate\Expression($expression, $params)
        );

        return $this;
    }

    public function all($identifier, string $operator, Select $select): self
    {
        $this->conditions->addPredicate(
            new Predicate\All($identifier, $operator, $select)
        );

        return $this;
    }

    public function any($identifier, string $operator, Select $select): self
    {
        $this->conditions->addPredicate(
            new Predicate\Any($identifier, $operator, $select)
        );

        return $this;
    }

    public function some($identifier, string $operator, Select $select): self
    {
        $this->conditions->addPredicate(
            new Predicate\Some($identifier, $operator, $select)
        );

        return $this;
    }

    public function between($identifier, array $limits): self
    {
        $this->conditions->addPredicate(
            new Predicate\Between($identifier, $limits)
        );

        return $this;
    }

    public function notBetween($identifier, array $limits): self
    {
        $this->conditions->addPredicate(
            new Predicate\NotBetween($identifier, $limits)
        );

        return $this;
    }

    public function exists(Select $select): self
    {
        $this->conditions->addPredicate(
            new Predicate\Exists($select)
        );

        return $this;
    }

    public function notExists(Select $select): self
    {
        $this->conditions->addPredicate(
            new Predicate\NotExists($select)
        );

        return $this;
    }

    public function in($identifier, array $value_list): self
    {
        $this->conditions->addPredicate(
            new Predicate\In($identifier, $value_list)
        );

        return $this;
    }

    public function notIn($identifier, array $value_list): self
    {
        $this->conditions->addPredicate(
            new Predicate\NotIn($identifier, $value_list)
        );

        return $this;
    }

    public function isNull($identifier): self
    {
        $this->conditions->addPredicate(
            new Predicate\IsNull($identifier)
        );

        return $this;
    }

    public function isNotNull($identifier): self
    {
        $this->conditions->addPredicate(
            new Predicate\IsNotNull($identifier)
        );

        return $this;
    }

    public function like($identifier, array $value): self
    {
        $this->conditions->addPredicate(
            new Predicate\Like($identifier, $value)
        );

        return $this;
    }

    public function notLike($identifier, array $value): self
    {
        $this->conditions->addPredicate(
            new Predicate\NotLike($identifier, $value)
        );

        return $this;
    }

    public function equal($identifier, array $value): self
    {
        $this->conditions->addPredicate(
            new Predicate\Comparison($identifier, Sql::EQUAL, $value)
        );

        return $this;
    }

    public function notEqual($identifier, array $value): self
    {
        $this->conditions->addPredicate(
            new Predicate\Comparison($identifier, Sql::NOT_EQUAL, $value)
        );

        return $this;
    }

    public function lessThan($identifier, array $value): self
    {
        $this->conditions->addPredicate(
            new Predicate\Comparison($identifier, Sql::LESS_THAN, $value)
        );

        return $this;
    }

    public function lessThanEqual($identifier, array $value): self
    {
        $this->conditions->addPredicate(
            new Predicate\Comparison($identifier, Sql::LESS_THAN_EQUAL, $value)
        );

        return $this;
    }

    public function greaterThanEqual($identifier, array $value): self
    {
        $this->conditions->addPredicate(
            new Predicate\Comparison($identifier, Sql::GREATER_THAN_EQUAL, $value)
        );

        return $this;
    }

    public function greaterThan($identifier, array $value): self
    {
        $this->conditions->addPredicate(
            new Predicate\Comparison($identifier, Sql::GREATER_THAN, $value)
        );

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
