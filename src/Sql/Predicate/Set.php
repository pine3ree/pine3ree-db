<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\Db\Sql\Predicate;

use ArrayIterator;
use Closure;
use IteratorAggregate;
use pine3ree\Db\Exception\InvalidArgumentException;
use pine3ree\Db\Exception\RuntimeException;
use pine3ree\Db\Sql;
use pine3ree\Db\Sql\Alias;
use pine3ree\Db\Sql\Clause\ConditionalClause;
use pine3ree\Db\Sql\Clause\ConditionalClauseAwareTrait;
use pine3ree\Db\Sql\Driver;
use pine3ree\Db\Sql\DriverInterface;
use pine3ree\Db\Sql\Identifier;
use pine3ree\Db\Sql\Literal;
use pine3ree\Db\Sql\Params;
use pine3ree\Db\Sql\Predicate;
use pine3ree\Db\Sql\Statement\Select;
use Throwable;
use Traversable;

use function count;
use function current;
use function explode;
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
 * @property-read array|Predicate[] $predicates An array of [(AND|OR), Predicate] added so far
 * @property-read ConditionalClause|null $clause Return the context of the parent conditional-clause, if any
*/
class Set extends Predicate implements IteratorAggregate
{
    /** @var Predicate[] */
    protected $predicates = [];

    /** Internal counter for predicate keys */
    protected int  $count = 0;

    protected string $defaultLogicalOperator = Sql::AND;

    protected ?string $nextLogicalOperator = null;

    /*
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
        'in'  => Sql::IN,
        'IN'  => Sql::IN,
        'notIn'  => Sql::NOT_IN,
        'NOTIN'  => Sql::NOT_IN,
        'NOT_IN' => Sql::NOT_IN,
        '~'        => Sql::LIKE,
        'notLike'  => Sql::NOT_LIKE,
        '!~'       => Sql::NOT_LIKE,
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
     *      new Predicate\Like('email', '%example.com'), // AND `email LIKE '%example.com'`
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
        if (null === $predicates || self::isEmptyPredicate($predicates, true)) {
            return;
        }

        if ($predicates instanceof self) {
            $predicateSet = $predicates;
            $this->count = $predicateSet->count;
            $this->defaultLogicalOperator = $defaultLogicalOperator ?? $predicateSet->defaultLogicalOperator;
            $predicates = $predicateSet->predicates;
            foreach ($predicates as $key => $predicate) {
                if ($predicate instanceof self) {
                    $predicate = clone $predicate;
                    $predicate->parent = $this;
                }
                $this->predicates[$key] = $predicate;
            }
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
     * Add a predicate or a predicate-set or predicate build specifications
     *
     * @param Predicate|string|array $predicate A Predicate|Predicate\Set instance
     *      or a specs-array [identifier, operator, value[, extra]] or [identifier => value]
     * @throws InvalidArgumentException
     * @return $this Provides fluent interface
     */
    public function addPredicate($predicate): self
    {
        self::assertValidPredicate($predicate);

        // allow adding empty nested-set (@see self::open())
        if (self::isEmptyPredicate($predicate, false)) {
            return $this; // throw?
        }

        if ($predicate instanceof Predicate) {
            if ($predicate->parentIsNot($this)) {
                $predicate = clone $predicate;
            }
        } else {
            $predicate = $this->buildPredicate($input = $predicate, false, false);
            if (! $predicate instanceof Predicate) {
                throw new InvalidArgumentException(sprintf(
                    "Adding a predicate must be done using either as a string, a"
                    . " Predicate|Predicate\Set instance or an predicate specs-array such as:"
                    . " [identifier, operator, value[, extra]]"
                    . " or [identifier => value]"
                    . " or ['&&', specs], ['||', specs]"
                    . " or ['&&' => [...], ['||' => [...],"
                    . " `%s` provided!",
                    is_object($input) ? get_class($input) : gettype($input)
                ));
            }
        }

        // attache the predicat to this set
        $predicate->parent = $this;

        $logicalOperator = $this->nextLogicalOperator ?? $this->defaultLogicalOperator;

        $this->count += 1;
        $key = "{$logicalOperator}:{$this->count}";
        $this->predicates[$key] = $predicate;

        $this->nextLogicalOperator = null;

        // remove rendered sql cache from tie element and its parent
        $this->clearSQL();

        return $this;
    }

    /**
     * Try to build a Predicate instance from specs
     *
     * @param mixed $predicate
     * @param bool $checkEmptyValue
     * @param bool $throw
     * @return Predicate|null
     * @throws InvalidArgumentException
     * @throws Throwable
     */
    protected function buildPredicate($predicate, bool $checkEmptyValue = false, bool $throw = true): ?Predicate
    {
        if ($checkEmptyValue && self::isEmptyPredicate($predicate)) {
            if ($throw) {
                throw new InvalidArgumentException(
                    "Cannot build a predicate from an empty input value!"
                );
            }
            return null;
        }

        try {
            if (is_string($predicate)) {
                return new Predicate\Literal($predicate);
            }
            if (is_array($predicate)) {
                return $this->buildPredicateFromSpecs($predicate);
            }
        } catch (Throwable $ex) {
            if ($throw) {
                throw $ex;
            }
            return null;
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
                $predicate = $this->buildPredicate($predicate, true, false);
            }

            if ($predicate instanceof Predicate) {
                $this->nextLogicalOperator = $logicalOp;
                return $predicate;
            }

            throw new InvalidArgumentException(
                "A predicate specs-array must be provided in one of the following forms: "
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

        self::assertValidOperator($operator);

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

        // check maxValue for BETWEEN predicates
        if (($count === 3 || $extra === null) && (
            $operator === Sql::BETWEEN || $operator === Sql::NOT_BETWEEN
        )) {
            throw new InvalidArgumentException(
                "Missing maxValue for `{$operator}` predicate specification!"
            );
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

        // not matched operator: should be unreacheable
        // @codeCoverageIgnoreStart
        throw new InvalidArgumentException(
            "Unsupported operator `{$operator}`!"
        );
        // @codeCoverageIgnoreEnd
    }

    /**
     * Will return true if the given predicate is not a valid type for building
     * a Predicate
     *
     * @param mixed $predicate
     * @param bool $checkEmptySet
     * @return bool
     */
    protected static function isEmptyPredicate($predicate, bool $checkEmptySet = false): bool
    {
        // empty values
        if ($predicate === null || $predicate === []) {
            return true;
        }

        // strings
        if (is_string($predicate)) {
            return trim($predicate) === '';
        }

        // predicates
        if ($predicate instanceof Predicate) {
            if ($checkEmptySet && $predicate instanceof Predicate\Set) {
                return $predicate->isEmpty();
            }
            return false;
        }

        // last valid type: not-empty array
        return !is_array($predicate);
    }

    /**
     * @param mixed $predicate
     *
     * @throws InvalidArgumentException
     */
    protected static function assertValidPredicate($predicate): void
    {
        if (is_string($predicate)
            || is_array($predicate)
            || $predicate instanceof Predicate
        ) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            "Invalid or unsupported predicate,"
            . " must be a string, a predicate or a predicate-specs array,"
            . " '%s' provided!",
            is_object($predicate) ? get_class($predicate) : gettype($predicate)
        ));
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

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->predicates);
    }

    public function getSQL(DriverInterface $driver = null, Params $params = null): string
    {
        if (empty($this->predicates)) {
            return $this->sql = '';
        }

        if (isset($this->sql) && $driver === $this->driver && $params === null) {
            return $this->sql;
        }

        $this->driver = $driver; // set last used driver argument
        $this->params = null; // reset previously collected params, if any

        $driver = $driver ?? Driver::ansi();
        $params = $params ?? ($this->params = new Params());

        $sqls = [];
        $count = 0;
        foreach ($this->predicates as $key => $predicate) {
            $logicalOperator = explode(':', $key)[0];
            $sql = $predicate->getSQL($driver, $params);
            if (self::isEmptySQL($sql)) {
                continue;
            }
            $count += 1;
            if ($count > 1) {
                $sqls[] = $logicalOperator;
            }
            $sqls[] = $predicate instanceof self ? "({$sql})" : $sql;
        }

        if (empty($sqls)) {
            return $this->sql = '';
        }

        if (1 === $count) {
            return $this->sql = current($sqls);
        }

        return $this->sql = trim(implode(' ', $sqls));
    }

    /**
     * Add a sql literal predicate
     *
     * @param string $literal
     * @return $this Fluent interface
     */
    public function literal(string $literal): self
    {
        return $this->addPredicate(
            new Predicate\Literal($literal)
        );
    }

    /**
     * Add a sql expression predicate
     *
     * @param string $expression
     * @param array $substitutions
     * @return $this Fluent interface
     */
    public function expression(string $expression, array $substitutions = []): self
    {
        return $this->addPredicate(
            new Predicate\Expression($expression, $substitutions)
        );
    }

    /**
     * @see of self::expression()
     *
     * @param string $expression
     * @param array $substitutions
     * @return $this Fluent interface
     */
    public function expr(string $expression, array $substitutions = []): self
    {
        return $this->expression($expression, $substitutions);
    }

    /**
     * Add a sql ALL comparison predicate
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param string $operator
     * @param Select $select
     * @return $this Fluent interface
     */
    public function all($identifier, string $operator, Select $select): self
    {
        return $this->addPredicate(
            new Predicate\All($identifier, $operator, $select)
        );
    }

    /**
     * Add a sql ANY comparison predicate
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param string $operator
     * @param Select $select
     * @return $this Fluent interface
     */
    public function any($identifier, string $operator, Select $select): self
    {
        return $this->addPredicate(
            new Predicate\Any($identifier, $operator, $select)
        );
    }

    /**
     * Add a sql SOME comparison predicate
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param string $operator
     * @param Select $select
     * @return $this Fluent interface
     */
    public function some($identifier, string $operator, Select $select): self
    {
        return $this->addPredicate(
            new Predicate\Some($identifier, $operator, $select)
        );
    }

    /**
     * Add a sql BETWEEN predicate
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param scalar|Literal $min_value
     * @param scalar|Literal $max_value
     * @return $this Fluent interface
     */
    public function between($identifier, $min_value, $max_value): self
    {
        return $this->addPredicate(
            new Predicate\Between($identifier, $min_value, $max_value)
        );
    }

    /**
     * Add a NOT BETWEEN predicate
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param scalar|Literal $min_value
     * @param scalar|Literal $max_value
     * @return $this Fluent interface
     */
    public function notBetween($identifier, $min_value, $max_value): self
    {
        return $this->addPredicate(
            new Predicate\NotBetween($identifier, $min_value, $max_value)
        );
    }

    /**
     * Add a sql EXISTS predicate
     *
     * @param Select $select
     * @return $this Fluent interface
     */
    public function exists(Select $select): self
    {
        return $this->addPredicate(
            new Predicate\Exists($select)
        );
    }

    /**
     * Add a NOT EXISTS predicate
     *
     * @param Select $select
     * @return $this Fluent interface
     */
    public function notExists(Select $select): self
    {
        return $this->addPredicate(
            new Predicate\NotExists($select)
        );
    }

    /**
     * Add a sql IN predicate
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param array|Select $valueList
     * @return $this Fluent interface
     */
    public function in($identifier, $valueList): self
    {
        return $this->addPredicate(
            new Predicate\In($identifier, $valueList)
        );
    }

    /**
     * Add a NOT IN predicate
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param array|Select $valueList
     * @return $this Fluent interface
     */
    public function notIn($identifier, $valueList): self
    {
        return $this->addPredicate(
            new Predicate\NotIn($identifier, $valueList)
        );
    }

    /**
     * Add a sql IS predicate
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param bool|null|string $value
     * @return $this Fluent interface
     */
    public function is($identifier, $value): self
    {
        return $this->addPredicate(
            new Predicate\Is($identifier, $value)
        );
    }

    /**
     * Add a sql IS NOT predicate
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param bool|null|string $value
     * @return $this Fluent interface
     */
    public function isNot($identifier, $value): self
    {
        return $this->addPredicate(
            new Predicate\IsNot($identifier, $value)
        );
    }

    /**
     * Add a sql IS NULL predicate
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @return $this Fluent interface
     */
    public function isNull($identifier): self
    {
        return $this->addPredicate(
            new Predicate\IsNull($identifier)
        );
    }

    /**
     * Add a sql IS NOT NULL predicate
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @return $this Fluent interface
     */
    public function isNotNull($identifier): self
    {
        return $this->addPredicate(
            new Predicate\IsNotNull($identifier)
        );
    }

    /**
     * Add a sql IS TRUE predicate
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @return $this Fluent interface
     */
    public function isTrue($identifier): self
    {
        return $this->addPredicate(
            new Predicate\IsTrue($identifier)
        );
    }

    /**
     * Add a sql IS FALSE predicate
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @return $this Fluent interface
     */
    public function isFalse($identifier): self
    {
        return $this->addPredicate(
            new Predicate\IsFalse($identifier)
        );
    }

    /**
     * Add a sql IS UNKNOWN predicate
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @return $this Fluent interface
     */
    public function isUnknown($identifier): self
    {
        return $this->addPredicate(
            new Predicate\IsUnknown($identifier)
        );
    }

    /**
     * Add a sql IS NOT UNKNOWN predicate
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @return $this Fluent interface
     */
    public function isNotUnknown($identifier): self
    {
        return $this->addPredicate(
            new Predicate\IsNotUnknown($identifier)
        );
    }

    /**
     * Add a sql LIKE predicate
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param string|Literal $pattern
     * @param string|null $escape
     * @return $this Fluent interface
     */
    public function like($identifier, $pattern, string $escape = null): self
    {
        return $this->addPredicate(
            new Predicate\Like($identifier, $pattern, $escape)
        );
    }

    /**
     * Add a NOT LIKE predicate
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param string|Literal $pattern
     * @param string|null $escape
     * @return $this Fluent interface
     */
    public function notLike($identifier, $pattern, string $escape = null): self
    {
        return $this->addPredicate(
            new Predicate\NotLike($identifier, $pattern, $escape)
        );
    }

    /**
     * Add an equal-to COMPARISON OPERATOR = predicate
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param scalar|Literal|Identifier|Alias|null $value
     * @return $this Fluent interface
     */
    public function equal($identifier, $value): self
    {
        return $this->addPredicate(
            new Predicate\Comparison($identifier, Sql::EQUAL, $value)
        );
    }

    /**
     * @see self::equal()
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param scalar|Literal|Identifier|Alias|null $value
     * @return $this Fluent interface
     */
    public function eq($identifier, $value): self
    {
        return $this->addPredicate(
            new Predicate\Comparison($identifier, Sql::EQ, $value)
        );
    }

    /**
     * Add a not-equal-to COMPARISON OPERATOR != predicate
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param scalar|Literal|Identifier|Alias|null $value
     * @return $this Fluent interface
     */
    public function notEqual($identifier, $value): self
    {
        return $this->addPredicate(
            new Predicate\Comparison($identifier, Sql::NOT_EQUAL, $value)
        );
    }

    /**
     * @see self::notEqual()
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param scalar|Literal|Identifier|Alias|null $value
     * @return $this Fluent interface
     */
    public function neq($identifier, $value): self
    {
        return $this->addPredicate(
            new Predicate\Comparison($identifier, Sql::NEQ, $value)
        );
    }

    /**
     * Add a not-equal-to COMPARISON OPERATOR <> predicate
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param scalar|Literal|Identifier|Alias|null $value
     * @return $this Fluent interface
     */
    public function ne($identifier, $value): self
    {
        return $this->addPredicate(
            new Predicate\Comparison($identifier, Sql::NE, $value)
        );
    }

    /**
     * Add a less-than COMPARISON OPERATOR < predicate
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param scalar|Literal|Identifier|Alias|null $value
     * @return $this Fluent interface
     */
    public function lessThan($identifier, $value): self
    {
        return $this->addPredicate(
            new Predicate\Comparison($identifier, Sql::LESS_THAN, $value)
        );
    }

    /**
     * @see self::lessThan()
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param scalar|Literal|Identifier|Alias|null $value
     * @return $this Fluent interface
     */
    public function lt($identifier, $value): self
    {
        return $this->addPredicate(
            new Predicate\Comparison($identifier, Sql::LT, $value)
        );
    }

    /**
     * Add a less-than-or-equal-to COMPARISON OPERATOR <= predicate
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param scalar|Literal|Identifier|Alias|null $value
     * @return $this Fluent interface
     */
    public function lessThanEqual($identifier, $value): self
    {
        return $this->addPredicate(
            new Predicate\Comparison($identifier, Sql::LESS_THAN_EQUAL, $value)
        );
    }

    /**
     * @see self::lessThanEqual()
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param scalar|Literal|Identifier|Alias|null $value
     * @return $this Fluent interface
     */
    public function lte($identifier, $value): self
    {
        return $this->addPredicate(
            new Predicate\Comparison($identifier, Sql::LTE, $value)
        );
    }

    /**
     * Add a greater-than-or-equal-to COMPARISON OPERATOR >= predicate
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param scalar|Literal|Identifier|Alias|null $value
     * @return $this Fluent interface
     */
    public function greaterThanEqual($identifier, $value): self
    {
        return $this->addPredicate(
            new Predicate\Comparison($identifier, Sql::GREATER_THAN_EQUAL, $value)
        );
    }

    /**
     * @see self::greaterThanEqual()
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param scalar|Literal|Identifier|Alias|null $value
     * @return $this Fluent interface
     */
    public function gte($identifier, $value): self
    {
        return $this->addPredicate(
            new Predicate\Comparison($identifier, Sql::GTE, $value)
        );
    }

    /**
     * Add a greater-than COMPARISON OPERATOR > predicate
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param scalar|Literal|Identifier|Alias|null $value
     * @return $this Fluent interface
     */
    public function greaterThan($identifier, $value): self
    {
        return $this->addPredicate(
            new Predicate\Comparison($identifier, Sql::GREATER_THAN, $value)
        );
    }

    /**
     * @see self::greaterThan()
     *
     * @param string|Alias|Identifier|Literal $identifier
     * @param scalar|Literal|Identifier|Alias|null $value
     * @return $this Fluent interface
     */
    public function gt($identifier, $value): self
    {
        return $this->addPredicate(
            new Predicate\Comparison($identifier, Sql::GT, $value)
        );
    }

    /**
     * Set AND as the logical operator for next predicate
     *
     * @return $this Fluent interface
     */
    public function and(): self
    {
        $this->nextLogicalOperator = Sql::AND;
        return $this;
    }

    /**
     * Set AND as the logical operator for next predicate
     *
     * @return $this Fluent interface
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
    public function beginGroup(string $defaultLogicalOperator = Sql::AND): self
    {
        $defaultLogicalOperator = self::COMB[strtoupper($defaultLogicalOperator)] ?? Sql::AND;

        $group = new self([], $defaultLogicalOperator);
        $this->addPredicate($group);

        return $group;
    }

    /**
     * Return to the original parent scope from a nested set scope
     *
     * @return self Return the parent predicate-set instance
     * @throws RuntimeException
     */
    public function endGroup(): self
    {
        if ($this->parent instanceof self) {
            return $this->parent;
        }

        throw new RuntimeException(
            "Cannot close an unnested predicate-set!"
        );
    }

    /**
     * Add a nested predicate-set, resulting in SQL conditions enclosed in parenthesis
     *
     * @param Closure $group An anonymous function for manipulating the newly created
     *      nested-set given its as argument
     * @param string $defaultLogicalOperator The default logical operator for the nested set
     *
     * @return $this Fluent interface
     */
    public function group(Closure $group, string $defaultLogicalOperator = Sql::AND): self
    {
        $nestedPredicateSet = $this->beginGroup($defaultLogicalOperator);
        $group($nestedPredicateSet);

        return $this;
    }

    /**
     * Set the context to the closest conditional-clause (where, having, on). if any
     *
     * @return ConditionalClause|null
     */
    public function clause(): ?ConditionalClause
    {
        return $this->closest(ConditionalClause::class, false);
    }

    public function __clone()
    {
        parent::__clone();
        foreach ($this->predicates as $key => $predicate) {
            $this->predicates[$key] = $predicate = clone $predicate;
            $predicate->parent = $this;
        }
    }

    /**
     * @return mixed
     */
    public function __get(string $name)
    {
        if ('clause' === $name) {
            return $this->clause();
        };

        if ('predicates' === $name) {
            return $this->predicates;
        };

        if ('nextLogicalOperator' === $name) {
            return $this->nextLogicalOperator;
        };

        if ('defaultLogicalOperator' === $name) {
            return $this->defaultLogicalOperator;
        };

        return parent::__get($name);
    }
}
