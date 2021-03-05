<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use InvalidArgumentException;
use P3\Db\Sql;
use P3\Db\Sql\Clause\ConditionalClauseAwareTrait;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Predicate;
use P3\Db\Sql\Statement\Select;

use function count;
use function current;
use function get_class;
use function gettype;
use function implode;
use function is_array;
use function is_numeric;
use function is_object;
use function is_string;
use function key;
use function sprintf;
use function strtoupper;
use function trim;

/**
 * Predicate\Set represents a group of predicates combined by AND and/or OR
 *
 * @property-read string $defaultLogicalOperator
 * @property-read string|null $nextLogicalOperator The next logical operator
 * @property-read array $predicates An array of [(AND|OR), Predicate] added so fare
*/
class Set extends Predicate
{
    /** @var Predicate[] */
    protected $predicates = [];

    /** @var string */
    protected $defaultLogicalOperator = Sql::AND;

    /** @var string|null */
    protected $nextLogicalOperator;

    /** @var self|null */
    protected $parent;

    /**
     * Logical operator aliases/identifiers for predicate-sets defined via arrays
     */
    public const COMB_AND = '&&';
    public const COMB_OR  = '||';

    /**
     * Aliases/identifiers ("||" for "OR" and "&&" for "AND") for nested-predicates
     * array specs mapped to the corresponding valid logical operators
     *
     * @see ConditionalClauseAwareTrait::setConditionalClause()
     */
    public const COMB_ID = [
        self::COMB_AND => Sql::AND,
        self::COMB_OR  => Sql::OR,
    ];

    /**
     * Complete map of operators/aliases to real/valid logical operators
     */
    protected const COMB = [
        Sql::AND => Sql::AND,
        Sql::OR  => Sql::OR,
        self::COMB_AND => Sql::AND,
        self::COMB_OR  => Sql::OR,
    ];

    private const OPERATOR_ALIAS = [
        'eq' => Sql::EQ,
        'EQ' => Sql::EQ,
        'notEqual'  => Sql::NOT_EQUAL,
        'NOTEQUAL'  => Sql::NOT_EQUAL,
        'NOT_EQUAL' => Sql::NOT_EQUAL,
        'neq' => Sql::NEQ,
        'NEQ' => Sql::NEQ,
        'ne' => Sql::NE,
        'NE' => Sql::NE,
        'lt' => Sql::LT,
        'LT' => Sql::LT,
        'lessThan'  => Sql::LESS_THAN,
        'LESSTHAN'  => Sql::LESS_THAN,
        'LESS_THAN' => Sql::LESS_THAN,
        'lte' => Sql::LTE,
        'LTE' => Sql::LTE,
        'lessThanEqual'   => Sql::LESS_THAN_EQUAL,
        'LESSTHANEQUAL'   => Sql::LESS_THAN_EQUAL,
        'LESS_THAN_EQUAL' => Sql::LESS_THAN_EQUAL,
        'gte' => Sql::GTE,
        'GTE' => Sql::GTE,
        'greaterThanEqual'   => Sql::GREATER_THAN_EQUAL,
        'GREATERTHANEQUAL'   => Sql::GREATER_THAN_EQUAL,
        'GREATER_THAN_EQUAL' => Sql::GREATER_THAN_EQUAL,
        'gt' => Sql::GT,
        'GT' => Sql::GT,
        'greaterThan'  => Sql::GREATER_THAN,
        'GREATERTHAN'  => Sql::GREATER_THAN,
        'GREATER_THAN' => Sql::GREATER_THAN,
        'not'    => Sql::IS_NOT,
        'NOT'    => Sql::IS_NOT,
        'isNot'  => Sql::IS_NOT,
        'ISNOT'  => Sql::IS_NOT,
        'IS_NOT' => Sql::IS_NOT,
        'notBetween'  => Sql::NOT_BETWEEN,
        'NOTBETWEEN'  => Sql::NOT_BETWEEN,
        'NOT_BETWEEN' => Sql::NOT_BETWEEN,
        'notIn'  => Sql::NOT_IN,
        'NOTIN'  => Sql::NOT_IN,
        'NOT_IN' => Sql::NOT_IN,
        'notLike'  => Sql::NOT_LIKE,
        'NOTLIKE'  => Sql::NOT_LIKE,
        'NOT_LIKE' => Sql::NOT_LIKE,
    ];

    /**
     * Array format when building the set from an array:
     *
     *  <pre>
     *  [
     *      'enabled' => true, // `enabled` = 1
     *      ['id', 'IN', [1, 2, 3, null]], // `id` AND IN ('1', '2', '3') OR `id` IS NULL
     *      new Predicate\Like('email', '%gmail.com'), // AND `email LIKE '%gmail.com'`
     *      ['||', ['id', '>', 1]], // OR `id` > 1
     *      // add a nested group with OR as default logical operator
     *      ['||' => [ // AND (
     *          ['status' => null], // `status` IS NULL
     *          ['status', 'IS', true], // OR `status` IS TRUE
     *          ['status', 'BETWEEN', 2, 16], // OR `status` BETWEEN 2 AND 16
     *          ['&&', "TRUE IS TRUE"], // AND TRUE IS TRUE
     *          new Predicate\Literal("created_at <= '2019-12-31'"), // OR `created_at` <= '2020-01-01'
     *      ]], // )
     *  ]
     * </pre>
     *
     * @param Predicate[]|self|Predicate|array|string|null $predicates
     * @param string|null $defaultLogicalOperator One of `AND`, `OR`, or aliases `&&`, `||`
     */
    public function __construct($predicates = null, string $defaultLogicalOperator = null)
    {
        if (isset($defaultLogicalOperator)) {
            $defaultLogicalOperator = self::COMB[strtoupper($defaultLogicalOperator)] ?? Sql::AND;
        }

        $this->defaultLogicalOperator = $defaultLogicalOperator ?? Sql::AND;

        // quick test for empty value
        if (Sql::isEmptyPredicate($predicates, true)) {
            return;
        }

        if ($predicates instanceof self) {
            $this->predicates = $predicates->getPredicates();
            $this->defaultLogicalOperator = $defaultLogicalOperator ?? $predicates->getDefaultLogicalOperator();
            return;
        }

        if (!is_array($predicates)) {
            $this->addPredicate($predicates);
            return;
        }

        foreach ($predicates as $key => $predicate) {
            if (is_numeric($key)) {
                $this->addPredicate($predicate);
                continue;
            }

            // $key is an identifier and $predicate is a value for the "=" or "IN" operator
            if (! $predicate instanceof Predicate) {
                $predicate = is_array($predicate)
                    ? new Predicate\In($key, $predicate)
                    : new Predicate\Comparison($key, Sql::EQ, $predicate);
            }

            $this->addPredicate($predicate);
        }
    }

    /**
     * Add a predicate or a predicate-set
     *
     * @param Predicate|string|array $predicate A Predicate|Predicate\Set instance
     *      or a specs-array [identifier, operator, value[, extra]] or [identifier => value]
     * @throws InvalidArgumentException
     * @return $this Provides fluent interface
     */
    public function addPredicate($predicate): self
    {
        if (Sql::isEmptyPredicate($predicate)) {
            return $this; // throw?
        }

        if (! $predicate instanceof Predicate) {
            $predicate = $this->buildPredicate($predicate, false, false);
            if (! $predicate instanceof Predicate) {
                throw new InvalidArgumentException(sprintf(
                    "Adding a predicate must be done using either as a string, a"
                    . " Predicate|Predicate\Set instance or an predicate specs-array such as:"
                    . " [identifier, operator, value[, extra]]"
                    . " or [identifier => value]"
                    . " or ['&&', specs], ['||', specs]"
                    . " or ['&&' => [...], ['||' => [...],"
                    . " `%s` provided!",
                    is_object($predicate) ? get_class($predicate) : gettype($predicate)
                ));
            }
        }

        $logicalOperator = $this->nextLogicalOperator ?? $this->defaultLogicalOperator;
        $this->predicates[] = [$logicalOperator, $predicate];
        $this->nextLogicalOperator = null;

        $this->sql = null; // remove rendered sql cache

        return $this;
    }

    protected function buildPredicate($predicate, bool $checkEmptyValue = false, bool $throw = true): ?Predicate
    {
        if ($checkEmptyValue && Sql::isEmptyPredicate($predicate)) {
            if ($throw) {
                throw new InvalidArgumentException(
                    "Cannot build a predicate from an empty input value!"
                );
            }
            return null;
        }

        if (is_string($predicate)) {
            return new Predicate\Literal($predicate);
        }

        if (is_array($predicate)) {
            return $this->buildPredicateFromSpecs($predicate);
        }

        if ($throw) {
            throw new InvalidArgumentException(sprintf(
                "Unable to create a predicate from given input value of type `%s`!",
                is_object($predicate) ? get_class($predicate)  : gettype($predicate)
            ));
        }

        return null;
    }

    protected function buildPredicateFromSpecs(array $specs): Predicate
    {
        if (empty($specs)) {
            throw new InvalidArgumentException(
                "Cannot build a predicate form an empty specs-array!"
            );
        }

        $count = count($specs);

        // CASE: [identifier => value]
        // CASE: ['&&' => [...]]
        // CASE: ['||' => [...]]
        if ($count === 1) {
            $key = key($specs);
            if (is_numeric($key)) {
                throw new InvalidArgumentException(
                    "A predicate single value specs-array must have a non-numeric"
                    . " string key, `{$key}` provided!"
                );
            }

            $value = current($specs);

            $logicalOp = self::COMB_ID[$key] ?? null;
            // CASES: ['&&' => [...]] and ['||' => [...]]
            if (isset($logicalOp)) {
                if (is_array($value)) {
                    return new self($nestedPredicates = $value, $logicalOp);
                }
                throw new InvalidArgumentException(
                    "A predicate single array-spec with `&&`, `||` as a key represents"
                    . " a group of predicates with that key as the default combination,"
                    . " therefore the value must be an array of nested predicates or specs!"
                );
            }

            // CASE: [identifier => value] where value is interpreted as an in-value-list array
            if (is_array($value)) {
                return new Predicate\In($key, $value);
            }

            // CASE: [identifier => value] simple equality check comparison
            return new Predicate\Comparison($key, Sql::EQ, $value);
        }

        // CASE: ['&&', predicateOrSpecs]
        // CASE: ['||', predicateOrSpecs]
        if ($count === 2) {
            $comb_id = $specs[0];
            $logicalOp = self::COMB_ID[$comb_id] ?? null;
            if (empty($logicalOp)) {
                throw new InvalidArgumentException(
                    "Invalid combination ID for predicate specs array with 2 values!"
                );
            }
            $predicate = $specs[1];
            if (! $predicate instanceof Predicate) {
                $predicate = $this->buildPredicate($predicate, true, true);
            }

            if ($predicate instanceof Predicate) {
                $this->nextLogicalOperator = $logicalOp;
                return $predicate;
            }

            throw new InvalidArgumentException(
                "A predicate specs-array must be provide in one of the following forms: "
                . " [identifier, operator, value[, extra]]"
                . " or [identifier => value]"
                . " or ['&&', predicateOrSpecs] or ['||', predicateOrSpecs]"
                . " or ['&&' => [...]] or ['||' => [...]]"
                . "!"
            );
        }

        // CASE: [identifier, operator, value]
        // CASE: [identifier, operator, value, extra]

        $identifier = $specs[0]; // identifier or Literal sql expression
        $operator   = $specs[1];
        $value      = $specs[2];
        $extra      = $specs[3] ?? null;

        $operator = self::OPERATOR_ALIAS[$operator]
            ?? self::OPERATOR_ALIAS[strtoupper($operator)]
            ?? strtoupper($operator);

        Sql::assertValidOperator($operator);

        if (isset(Sql::COMPARISON_OPERATORS[$operator])) {
            if (is_array($value)) {
                if ($operator === Sql::EQ) {
                    return new Predicate\In($identifier, $value);
                }
                if ($operator === Sql::NEQ || $operator === Sql::NE) {
                    return new Predicate\NotIn($identifier, $value);
                }
                throw new InvalidArgumentException(
                    "Invalid comparison operator `{$operator}` for array in-value-list!"
                );
            }
            return new Predicate\Comparison($identifier, $operator, $value);
        }

        if (isset(Sql::BOOLEAN_OPERATORS[$operator])) {
            if ($operator === Sql::IS) {
                return new Predicate\Is($identifier, $value);
            }
            return new Predicate\IsNot($identifier, $value);
        }

        switch ($operator) {
            case Sql::BETWEEN:
                return new Predicate\Between($identifier, $min = $value, $max = $extra);
            case Sql::NOT_BETWEEN:
                return new Predicate\NotBetween($identifier, $min = $value, $max = $extra);
            case Sql::IN:
                return new Predicate\In($identifier, $value);
            case Sql::NOT_IN:
                return new Predicate\NotIn($identifier, $value);
            case Sql::LIKE:
                return new Predicate\Like($identifier, $value, $escape = $extra);
            case Sql::NOT_LIKE:
                return new Predicate\NotLike($identifier, $value, $escape = $extra);
        }

        // other not matched operators must use a scalar or literal value
        if (is_array($value)) {
            throw new InvalidArgumentException(
                "Array value not supported for operator `{$operator}`!"
            );
        }

        if ($value instanceof Literal) {
            return new Predicate\Literal("{$identifier} {$operator} {$value->getSQL()}");
        }

        $marker = $this->createParam($value, null, 'expr');
        $params[] = [$marker => $value];

        return new Predicate\Expression("{$identifier} {$operator} {$marker}", $params);
    }

    /**
     * @return string Returns either "AND" or "OR"
     */
    public function getDefaultLogicalOperator(): string
    {
        return $this->defaultLogicalOperator;
    }

    /**
     * @return string Returns either null or "AND" or "OR"
     */
    public function getNextLogicalOperator(): ?string
    {
        return $this->nextLogicalOperator;
    }

    /**
     * @return Predicate[]
     */
    public function getPredicates(): array
    {
        return $this->predicates;
    }

    public function isEmpty(): bool
    {
        return empty($this->predicates);
    }

    public function getSQL(Driver $driver = null): string
    {
        if (isset($this->sql)) {
            return $this->sql;
        }

        if (empty($this->predicates)) {
            return $this->sql = '';
        }

        $this->resetParams();

        $driver = $driver ?? Driver::ansi();

        $sqls = [];
        foreach ($this->predicates as $index => $p) {
            $logicalOperator = $p[0];
            $predicate = $p[1];
            $sql = $predicate->getSQL($driver);
            if (Sql::isEmptySQL($sql)) {
                continue;
            }
            if ($index > 0) {
                $sqls[] = $logicalOperator;
            }
            $sqls[] = $predicate instanceof self ? "({$sql})" : $sql;
            $this->importParams($predicate);
        }

        if (empty($sqls)) {
            return $this->sql = '';
        }

        if (1 === count($sqls)) {
            return $this->sql = current($sqls);
        }

        return $this->sql = trim(implode(' ', $sqls));
    }

    public function literal(string $literal): self
    {
        return $this->addPredicate(
            new Predicate\Literal($literal)
        );
    }

    public function expression(string $expression, array $substitutions = []): self
    {
        return $this->addPredicate(
            new Predicate\Expression($expression, $substitutions)
        );
    }

    /**
     * @alias of self::expression()
     */
    public function expr(string $expression, array $substitutions = []): self
    {
        return $this->expression($expression, $substitutions);
    }

    public function all($identifier, string $operator, Select $select): self
    {
        return $this->addPredicate(
            new Predicate\All($identifier, $operator, $select)
        );
    }

    public function any($identifier, string $operator, Select $select): self
    {
        return $this->addPredicate(
            new Predicate\Any($identifier, $operator, $select)
        );
    }

    public function some($identifier, string $operator, Select $select): self
    {
        return $this->addPredicate(
            new Predicate\Some($identifier, $operator, $select)
        );
    }

    public function between($identifier, $min_value, $max_value): self
    {
        return $this->addPredicate(
            new Predicate\Between($identifier, $min_value, $max_value)
        );
    }

    public function notBetween($identifier, $min_value, $max_value): self
    {
        return $this->addPredicate(
            new Predicate\NotBetween($identifier, $min_value, $max_value)
        );
    }

    public function exists(Select $select): self
    {
        return $this->addPredicate(
            new Predicate\Exists($select)
        );
    }

    public function notExists(Select $select): self
    {
        return $this->addPredicate(
            new Predicate\NotExists($select)
        );
    }

    public function in($identifier, array $value_list): self
    {
        return $this->addPredicate(
            new Predicate\In($identifier, $value_list)
        );
    }

    public function notIn($identifier, array $value_list): self
    {
        return $this->addPredicate(
            new Predicate\NotIn($identifier, $value_list)
        );
    }

    public function is($identifier, $value): self
    {
        return $this->addPredicate(
            new Predicate\Is($identifier, $value)
        );
    }

    public function isNot($identifier, $value): self
    {
        return $this->addPredicate(
            new Predicate\IsNot($identifier, $value)
        );
    }

    public function isNull($identifier): self
    {
        return $this->addPredicate(
            new Predicate\IsNull($identifier)
        );
    }

    public function isNotNull($identifier): self
    {
        return $this->addPredicate(
            new Predicate\IsNotNull($identifier)
        );
    }

    public function isTrue($identifier): self
    {
        return $this->addPredicate(
            new Predicate\IsTrue($identifier)
        );
    }

    public function isFalse($identifier): self
    {
        return $this->addPredicate(
            new Predicate\IsFalse($identifier)
        );
    }

    public function isUnknown($identifier): self
    {
        return $this->addPredicate(
            new Predicate\IsUnknown($identifier)
        );
    }

    public function isNotUnknown($identifier): self
    {
        return $this->addPredicate(
            new Predicate\IsNotUnknown($identifier)
        );
    }

    public function like($identifier, $value, string $escape = null): self
    {
        return $this->addPredicate(
            new Predicate\Like($identifier, $value, $escape)
        );
    }

    public function notLike($identifier, $value, string $escape = null): self
    {
        return $this->addPredicate(
            new Predicate\NotLike($identifier, $value, $escape)
        );
    }

    public function equal($identifier, $value): self
    {
        return $this->addPredicate(
            new Predicate\Comparison($identifier, Sql::EQUAL, $value)
        );
    }

    public function eq($identifier, $value): self
    {
        return $this->addPredicate(
            new Predicate\Comparison($identifier, Sql::EQ, $value)
        );
    }

    public function notEqual($identifier, $value): self
    {
        return $this->addPredicate(
            new Predicate\Comparison($identifier, Sql::NOT_EQUAL, $value)
        );
    }

    public function neq($identifier, $value): self
    {
        return $this->addPredicate(
            new Predicate\Comparison($identifier, Sql::NEQ, $value)
        );
    }

    public function ne($identifier, $value): self
    {
        return $this->addPredicate(
            new Predicate\Comparison($identifier, Sql::NE, $value)
        );
    }

    public function lessThan($identifier, $value): self
    {
        return $this->addPredicate(
            new Predicate\Comparison($identifier, Sql::LESS_THAN, $value)
        );
    }

    public function lt($identifier, $value): self
    {
        return $this->addPredicate(
            new Predicate\Comparison($identifier, Sql::LT, $value)
        );
    }

    public function lessThanEqual($identifier, $value): self
    {
        return $this->addPredicate(
            new Predicate\Comparison($identifier, Sql::LESS_THAN_EQUAL, $value)
        );
    }

    public function lte($identifier, $value): self
    {
        return $this->addPredicate(
            new Predicate\Comparison($identifier, Sql::LTE, $value)
        );
    }

    public function greaterThanEqual($identifier, $value): self
    {
        return $this->addPredicate(
            new Predicate\Comparison($identifier, Sql::GREATER_THAN_EQUAL, $value)
        );
    }

    public function gte($identifier, $value): self
    {
        return $this->addPredicate(
            new Predicate\Comparison($identifier, Sql::GTE, $value)
        );
    }

    public function greaterThan($identifier, $value): self
    {
        return $this->addPredicate(
            new Predicate\Comparison($identifier, Sql::GREATER_THAN, $value)
        );
    }

    public function gt($identifier, $value): self
    {
        return $this->addPredicate(
            new Predicate\Comparison($identifier, Sql::GT, $value)
        );
    }

    public function regExp($identifier, array $regexp, bool $case_sensitive = false): self
    {
        return $this->addPredicate(
            new Predicate\RegExp($identifier, $regexp, $case_sensitive)
        );
    }

    public function notRegExp($identifier, array $regexp, bool $case_sensitive = false): self
    {
        return $this->addPredicate(
            new Predicate\NotRegExp($identifier, $regexp, $case_sensitive)
        );
    }

    /**
     * Set AND as the logical operator for next predicate
     *
     * @return $this fluent interface
     */
    public function and(): self
    {
        $this->nextLogicalOperator = Sql::AND;
        return $this;
    }

    /**
     * Set AND as the logical operator for next predicate
     *
     * @return $this fluent interface
     */
    public function or(): self
    {
        $this->nextLogicalOperator = Sql::OR;
        return $this;
    }

    /**
     * Add a nested predicate-set, creating the effect of a SQL opening parenthesis
     *
     * @param string $defaultLogicalOperator The default logical operator for the nested set
     *
     * @return self Return the new nested predicate-set instance
     */
    public function open(string $defaultLogicalOperator = Sql::AND): self
    {
        $defaultLogicalOperator = self::COMB[strtoupper($defaultLogicalOperator)] ?? Sql::AND;

        $nestedPredicateSet = new self([], $defaultLogicalOperator);
        $this->addPredicate($nestedPredicateSet);
        $nestedPredicateSet->parent = $this;

        return $nestedPredicateSet;
    }

    /**
     * Close a previously opened  nested predicate-set, creating the effect of a
     * SQL closing parenthesis
     *
     * @return $this fluent interface
     * @throws RuntimeException
     */
    public function close(): self
    {
        if (null === $this->parent) {
            throw new RuntimeException(
                "Cannot close an unnested predicate-set!"
            );
        }

        return $this->parent;
    }

    public function __clone()
    {
        parent::__clone();
        foreach ($this->predicates as $i => $predicate) {
            $this->predicates[$i] = [$predicate[0], clone $predicate[1]];
        }
    }

    public function __get(string $name)
    {
        if ('predicates' === $name) {
            return $this->predicates;
        };

        if ('nextLogicalOperator' === $name) {
            return $this->defaultLogicalOperator;
        };

        if ('defaultLogicalOperator' === $name) {
            return $this->defaultLogicalOperator;
        };

        throw new RuntimeException(
            "Undefined property {$name}!"
        );
    }
}
