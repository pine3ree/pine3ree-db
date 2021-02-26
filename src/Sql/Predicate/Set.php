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
use function strtolower;
use function strtoupper;
use function trim;

/**
 * Predicate\Set represents a group of predicates combined either by AND or OR
 *
 * @property-read self $and
 * @property-read self $or
 * @property-read self $open
 * @property-read self $close
*/
class Set extends Predicate
{
    /** @var Predicate[] */
    protected $predicates = [];

    /** @var string */
    protected $defaultLogicalOperator = Sql::AND;

    /** @var string */
    protected $nextLogicalOperator = Sql::AND;

    /** @var self|null */
    protected $parent;

    /**
     * Logical operator aliases for predicate-sets defined via arrays
     */
    public const COMB_AND = '&&';
    public const COMB_OR  = '||';

    /**
     * Complete map of operators/aliases to real/valid logical operators
     */
    protected const COMB = [
        Sql::AND => Sql::AND,
        Sql::OR  => Sql::OR,
        self::COMB_AND => Sql::AND,
        self::COMB_OR  => Sql::OR,
    ];

    /**
     * Aliases ("||" for "OR" and "&&" for "AND") for nested-predicates array
     * definitions mapped to the corresponding valid logical operators
     *
     * @see ConditionalClauseAwareTrait::setConditionalClause()
     */
    public const COMB_ID = [
        self::COMB_AND => Sql::AND,
        self::COMB_OR  => Sql::OR,
    ];

    private const OPERATOR_ALIAS = [
        'eq'          => Sql::EQ,
        'EQ'          => Sql::EQ,
        'notEqual'    => Sql::NOT_EQUAL,
        'neq'         => Sql::NEQ,
        'NEQ'         => Sql::NEQ,
        'ne'          => Sql::NE,
        'NE'          => Sql::NE,
        'lt'          => Sql::LT,
        'LT'          => Sql::LT,
        'lte'         => Sql::LTE,
        'LTE'         => Sql::LTE,
        'gte'         => Sql::GTE,
        'GTE'         => Sql::GTE,
        'gt'          => Sql::GT,
        'GT'          => Sql::GT,
        'not'         => Sql::IS_NOT,
        'NOT'         => Sql::IS_NOT,
        'isNot'       => Sql::IS_NOT,
        'notBetween'  => Sql::NOT_BETWEEN,
        'notIn'       => Sql::NOT_IN,
        'notLike'     => Sql::NOT_LIKE,
        'regExpCs'    => Sql::REGEXP_CS,
        'notRegExp'   => Sql::NOT_REGEXP,
        'notRegExpCs' => Sql::NOT_REGEXP_CS,
    ];

    /**
     * Array format when building the set from an array:
     *
     *  <pre>
     *  [
     *      'enabled' => true, // `enabled` = 1
     *      ['id', 'IN', [1, 2, 3, null]], // `id` AND IN (1, 2, 3) OR `id` IS NULL
     *      new Predicate\Like('email', '%gmail.com'), // AND `email LIKE '%gmail.com'`
     *      ['||' => [
     *          ['status' => null], // `status` IS NULL
     *          ['status', 'IS', true], // OR `status` IS TRUE
     *          ['status', 'BETWEEN', 2, 16], // OR `status` BETWEEN '2' AND '16'
     *          new Predicate\Literal("created_at <= '2019-12-31'"), // OR `created_at` <= '2020-01-01'
     *      ]].
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

        if (!isset($predicates)) {
            return;
        }

        if ($predicates instanceof self) {
            $this->predicates = $predicates->getPredicates();
            $this->defaultLogicalOperator = $defaultLogicalOperator ?? $predicates->getDefaultLogicalOperator();
            return;
        }

        $this->defaultLogicalOperator = $defaultLogicalOperator ?? Sql::AND;

        if (!is_array($predicates)) {
            $this->addPredicate($predicates);
            return;
        }

        foreach ($predicates as $key => $predicate) {
            // nested predicate-set
            if ($predicate instanceof self) {
                $this->addPredicate($predicate);
                continue;
            }

            if (is_numeric($key) || is_array($predicate)) {
                $this->addPredicate($predicate);
                continue;
            }

            // $key is an identifier and $predicate is a value for the '=' operator
            if (! $predicate instanceof Predicate) {
                $predicate = new Predicate\Comparison($key, '=', $predicate);
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
        if (is_string($predicate)) {
            $predicate = new Predicate\Literal($predicate);
        } elseif (is_array($predicate)) {
            $predicate = $this->buildPredicateFromSpecs($predicate);
        }

        if (! $predicate instanceof Predicate) {
            throw new InvalidArgumentException(sprintf(
                "Adding a predicate must be done using either as a string, a"
                . " Predicate|Predicate\Set instance or an predicate specs-array such as "
                . "[identifier, operator, value[, extra]] or [identifier => value] or ['||' => [...],"
                . " `%s` provided!",
                is_object($predicate) ? get_class($predicate) : gettype($predicate)
            ));
        }

        $logicalOperator = $this->nextLogicalOperator ?? $this->defaultLogicalOperator;
        $this->predicates[] = [$logicalOperator, $predicate];
        $this->nextLogicalOperator = null;

        $this->sql = null; // remove rendered sql cache

        return $this;
    }

    protected function buildPredicateFromSpecs(array $specs): Predicate
    {
        if (count($specs) === 1) {
            $key = key($specs);
            if (!is_numeric($key)) {
                $logicalOp = self::COMB_ID[$key] ?? null;
                if (isset($logicalOp)) {
                    return new self(current($specs), $logicalOp);
                }
                return new Predicate\Comparison($key, '=', current($specs));
            }
            throw new InvalidArgumentException(sprintf(
                "A predicate single value specs-array must have a non-numeric string key, `%s` provided",
                $key
            ));
        }

        $count = count($specs);

        if (count($specs) < 3) {
            throw new InvalidArgumentException(
                "A predicate specs-array must be provide in one of the following forms"
                . " [identifier, operator, value[, extra]] or [identifier => value] or ['||' => [...]]!"
            );
        }

        $identifier = $specs[0]; // identifier or Literal sql expression
        $operator   = $specs[1];
        $value      = $specs[2];
        $extra      = $specs[3] ?? null;

        $operator = self::OPERATOR_ALIAS[$operator]
            ?? self::OPERATOR_ALIAS[strtoupper($operator)]
            ?? strtoupper($operator)
        ;
        Sql::assertValidOperator($operator);

        if (isset(Sql::COMPARISON_OPERATORS[$operator])) {
            return new Predicate\Comparison($identifier, $operator, $value);
        }

        if (isset(Sql::BOOLEAN_OPERATORS[$operator])) {
            if ($operator === SQL::IS) {
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
            case Sql::REGEXP:
                return new Predicate\RegExp($identifier, $value, false);
            case Sql::NOT_REGEXP:
                return new Predicate\NotRegExp($identifier, $value, false);
            case Sql::REGEXP_CS:
                return new Predicate\RegExp($identifier, $value, true);
            case Sql::NOT_REGEXP_CS:
                return new Predicate\NotRegExp($identifier, $value, true);
        }

        if (is_array($value)) {
             throw new InvalidArgumentException(
                "Array value not supported for operator `{$operator}`!"
            );
        }

        if ($value instanceof Literal) {
            $literal = $value->getSQL();
            return new Predicate\Literal("{$identifier} {$operator} {$literal}");
        }

        $marker = $this->createNamedParam($value);
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

    public function expression(string $expression, array $params = []): self
    {
        return $this->addPredicate(
            new Predicate\Expression($expression, $params)
        );
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
     * @return self Return the new nested predicate-set instance
     */
    public function open(): self
    {
        $nestedPredicateSet = new self();
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

    /**
     * Provide a fluent interface for conditions using virtual properties
     *
     * @param string $name
     */
    public function __get(string $name)
    {
        if ('open' === $name) {
            return $this->open();
        };
        if ('close' === $name) {
            return $this->close();
        };

        $lcName = strtolower($name);
        if ('and' === $lcName) {
            return $this->and();
        };
        if ('or' === $lcName) {
            return $this->or();
        };
    }
}
