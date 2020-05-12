<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use InvalidArgumentException;
use P3\Db\Sql;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Predicate;
use P3\Db\Sql\Statement\Select;
use P3\Db\Sql\Statement\Traits\ConditionAwareTrait;

use function array_unshift;
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
 * Predicate\Set represents a group of predicates combined either by AND or OR
 */
class Set extends Predicate
{
    /** @var Predicate[] */
    protected $predicates = [];

    /** @var string */
    protected $combined_by;

    /**
     * Logical operator aliases for predicate-sets defined via arrays
     */
    public const COMB_AND = '&&';
    public const COMB_OR  = '||';

    protected const COMB = [
        Sql::AND => Sql::AND,
        Sql::OR  => Sql::OR,
        self::COMB_AND => Sql::AND,
        self::COMB_OR  => Sql::OR,
    ];

    private const OPERATOR_ALIAS = [
        'notBetween' => Sql::NOT_BETWEEN,
        'notIn' => Sql::NOT_IN,
        'notLike' => Sql::NOT_LIKE,
    ];

    /**
     * ALiases ("||" for "OR" and "&&" for "AND") for nested-predicates array
     * definitons
     *
     * @see ConditionAwareTrait::setCondition()
     */
    public const COMB_ID = [
        self::COMB_AND => self::COMB_AND,
        self::COMB_OR  => self::COMB_OR,
    ];

    /**
     * Array format when building the set from an array:
     *
     *  <pre>
     *  [
     *      'enabled' => true, // `enabled` = 1
     *      ['id', 'IN', [1, 2, 3, null]], // `id` AND IN (1, 2, 3) OR `id` IS NULL
     *      new Predicate\Like('email', '%gmail.com'), // AND `email LIKE '%gmail.com'`
     *      '!!' => [
     *          'status' => [
     *              ['=', null], // `status` IS NULL
     *              ['BETWEEN', [2, 16]], // OR `status` BETWEEN '2' AND '16'
     *          ],
     *          new Predicate\Literal("created_at <= '2019-12-31'"), // OR `created_at` <= '2020-01-01'
     *      ].
     *  ]
     * </pre>
     *
     * @param string $combined_by One of `AND`, `OR`, `&&`, `||`
     * @param null|Predicate[]|self|Predicate|array|string $predicates
     */
    public function __construct(string $combined_by = null, $predicates = null)
    {
        if (isset($combined_by)) {
            $combined_by = self::COMB[strtoupper($combined_by)] ?? Sql::AND;
        }

        if (!isset($predicates)) {
            return;
        }

        if ($predicates instanceof self) {
            $this->predicates  = $predicates->getPredicates();
            $this->combined_by = $combined_by ?? $predicates->getCombinedBy();
            return;
        }

        $this->combined_by = $combined_by ?? Sql::AND;

        if (!is_array($predicates)) {
            $this->addPredicate($predicates);
            return;
        }

        foreach ($predicates as $key => $predicate) {
            // nested predicate-set
            if ($predicate instanceof self) {
                $comb_by = self::COMB_ID[$key] ?? null;
                if (isset($comb_by) && $comb_by !== $predicate->getCombinedBy()) {
                    $nestedSet = new self($comb_by, $predicate->getPredicates());
                    $this->addPredicate($nestedSet);
                } else {
                    $this->addPredicate($predicate);
                }
                continue;
            }

            if (is_numeric($key)) {
                $this->addPredicate($predicate);
                continue;
            }

            if (is_array($predicate)) {
                // $key is "||" or "&&" for predicate-set array definitions
                $comb_by = self::COMB_ID[$key] ?? null;
                if (isset($comb_by)) {
                    $nestedSet = new self($comb_by, $predicate);
                    $this->addPredicate($nestedSet);
                    continue;
                }

                // $key is an identifier and the array may be a predicate-building
                // spec in the form [operator, value] that allows to set multiple
                // conditions for a single identifier
                foreach ($predicate as $specs) {
                    if (is_array($specs) && 2 === count($specs)) {
                        array_unshift($specs, $key);
                        $this->addPredicate($specs);
                    }
                }
                continue;
            }

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
     *      or a specs-array [identifier, operator, value] or [identifier => value]
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
                . "[identifier, operator, value] or [identifier => value], `%s` provided!",
                is_object($predicate) ? get_class($predicate) : gettype($predicate)
            ));
        }

        $this->predicates[] = $predicate;
        $this->sql = null; // remove rendered sql

        return $this;
    }

    protected function buildPredicateFromSpecs(array $specs): Predicate
    {
        if (count($specs) === 1) {
            if (!is_numeric($key = key($specs))) {
                return new Predicate\Comparison($key, '=', current($specs));
            }
            throw new InvalidArgumentException(sprintf(
                "A predicate single value specs-array must have a non-numeric string key, `%s` provided",
                $key
            ));
        }

        if (count($specs) !== 3) {
            throw new InvalidArgumentException(
                "A predicate specs-array must be provide in one of the following forms"
                . " [identifier, operator, value] or [identifier => value]!"
            );
        }

        $identifier = $specs[0]; // identifier or Literal sql expression
        $operator   = $specs[1];
        $value      = $specs[2];

        $operator = self::OPERATOR_ALIAS[$operator] ?? strtoupper($operator);

        Sql::assertValidOperator($operator);

        if (isset(Sql::COMPARISON_OPERATORS[$operator])) {
            return new Predicate\Comparison($identifier, $operator, $value);
        }

        switch ($operator) {
            case Sql::BETWEEN:
                return new Predicate\Between($identifier, $value);
            case Sql::NOT_BETWEEN:
                return new Predicate\NotBetween($identifier, $value);
            case Sql::IN:
                return new Predicate\In($identifier, $value);
            case Sql::NOT_IN:
                return new Predicate\NotIn($identifier, $value);
            case Sql::LIKE:
                return new Predicate\Like($identifier, $value);
            case Sql::NOT_LIKE:
                return new Predicate\NotLike($identifier, $value);
        }

        if (is_array($value)) {
             throw new InvalidArgumentException(
                "Array value not supported for operator `{$operator}`!"
            );
        }

        if ($value instanceof Literal) {
            $value_sql = $value->getSQL();
            return new Predicate\Literal("{$identifier} {$operator} {$value_sql}");
        }

        $marker = $this->createNamedParam($value);
        $params[] = [$marker => $value];

        return new Predicate\Expression("{$identifier} {$operator} {$marker}", $params);
    }

    /**
     * @return string Returns either "AND" or "OR"
     */
    public function getCombinedBy(): string
    {
        return $this->combined_by;
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

        $driver = $driver ?? Driver::ansi();

        //return $this->sql = $driver->getPredicateSetSQL($this);

        $sqls = [];
        foreach ($this->predicates as $predicate) {
            $sql = $predicate->getSQL($driver);
            if (Sql::isEmptySQL($sql)) {
                continue;
            }
            $sqls[] = $sql;
            $this->importParams($predicate);
        }

        if (empty($sqls)) {
            return $this->sql = '';
        }

        if (1 === count($sqls)) {
            return $this->sql = $sqls[0];
        }

        $AND_OR = self::COMB[$this->combined_by];

        return $this->sql = "(" . trim(implode(" {$AND_OR} ", $sqls)) . ")";
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

    public function between($identifier, array $limits): self
    {
        return $this->addPredicate(
            new Predicate\Between($identifier, $limits)
        );
    }

    public function notBetween($identifier, array $limits): self
    {
        return $this->addPredicate(
            new Predicate\NotBetween($identifier, $limits)
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

    public function like($identifier, array $value): self
    {
        return $this->addPredicate(
            new Predicate\Like($identifier, $value)
        );
    }

    public function notLike($identifier, array $value): self
    {
        return $this->addPredicate(
            new Predicate\NotLike($identifier, $value)
        );
    }

    public function equal($identifier, array $value): self
    {
        return $this->addPredicate(
            new Predicate\Comparison($identifier, Sql::EQUAL, $value)
        );
    }

    public function notEqual($identifier, array $value): self
    {
        return $this->addPredicate(
            new Predicate\Comparison($identifier, Sql::NOT_EQUAL, $value)
        );
    }

    public function lessThan($identifier, array $value): self
    {
        return $this->addPredicate(
            new Predicate\Comparison($identifier, Sql::LESS_THAN, $value)
        );
    }

    public function lessThanEqual($identifier, array $value): self
    {
        return $this->addPredicate(
            new Predicate\Comparison($identifier, Sql::LESS_THAN_EQUAL, $value)
        );
    }

    public function greaterThanEqual($identifier, array $value): self
    {
        return $this->addPredicate(
            new Predicate\Comparison($identifier, Sql::GREATER_THAN_EQUAL, $value)
        );
    }

    public function greaterThan($identifier, array $value): self
    {
        return $this->addPredicate(
            new Predicate\Comparison($identifier, Sql::GREATER_THAN, $value)
        );
    }
}
