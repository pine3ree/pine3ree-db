<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Clause;

use P3\Db\Sql;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Element;
use P3\Db\Sql\Predicate;

use function ltrim;
use function preg_replace;
use function strrchr;
use function strtoupper;

/**
 * This class abstracts the SQL conditional clauses WHERE, HAVING and ON
 */
abstract class ConditionalClause extends Element
{
    /**
     * @var string The SQL-clause name: WHERE|HAVING|ON condition clauses
     */
    protected static $name;

    /**
     * @var string WHERE|HAVING|ON Resolved name cache
     */
    protected $__name;

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
     * Return the SQL name for the clause (uppercase class-basename)
     * @return string
     */
    protected function getName(): string
    {
        // use the statically defined name if set
        if (isset(static::$name)) {
            return static::$name;
        }

        // use the cached name value if set
        if (isset($this->__name)) {
            return $this->__name;
        }

        $class_basename = ltrim(strrchr(static::class, '\\'), '\\');
        $name = preg_replace('/[a-z][A-Z]/', '$1 $2', $class_basename);
        $this->__name = strtoupper($name);

        return $this->__name;
    }

    public function __call(string $methodName, array $arguments)
    {
        switch ($methodName) {
            case 'all':
               return $this->conditions->all(...$arguments);

            case 'any':
                return $this->conditions->any(...$arguments);

            case 'between':
                return $this->conditions->between(...$arguments);

            case 'equal':
                return $this->conditions->equal(...$arguments);

            case 'exists':
                return $this->conditions->exists(...$arguments);

            case 'expression':
                return $this->conditions->expression(...$arguments);

            case 'greaterThan':
                return $this->conditions->greaterThan(...$arguments);

            case 'greaterThanEqual':
                return $this->conditions->greaterThanEqual(...$arguments);

            case 'in':
                return $this->conditions->in(...$arguments);

            case 'isNotNull':
                return $this->conditions->isNotNull(...$arguments);

            case 'isNull':
                return $this->conditions->isNull(...$arguments);

            case 'lessThan':
                return $this->conditions->lessThan(...$arguments);

            case 'lessThanEqual':
                return $this->conditions->lessThanEqual(...$arguments);

            case 'like':
                return $this->conditions->like(...$arguments);

            case 'literal':
                return $this->conditions->literal(...$arguments);

            case 'notBetween':
                return $this->conditions->notBetween(...$arguments);

            case 'notEqual':
                return $this->conditions->notEqual(...$arguments);

            case 'notBetween':
                return $this->conditions->notBetween(...$arguments);

            case 'notExists':
                return $this->conditions->notExists(...$arguments);

            case 'notIn':
                return $this->conditions->notIn(...$arguments);

            case 'notLike':
                return $this->conditions->notLike(...$arguments);
        }
    }
}
