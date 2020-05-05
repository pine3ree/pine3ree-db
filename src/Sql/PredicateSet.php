<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use InvalidArgumentException;
use P3\Db\Sql;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Predicate;

/**
 * Class PredicateSet
 */
class PredicateSet extends Predicate
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

    /**
     * ALiases ("||" for "OR" and "&&" for "AND") for nested-predicates array
     * definitons
     *
     * @see \P3\Db\Sql\Statement\Traits\ConditionAwareTrait::setCondition()
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
     * @param string $combined_by
     * @param null|Predicate[]|PredicateSet|Predicate|array|string $predicates
     */
    public function __construct(string $combined_by = null, $predicates = null)
    {
        if (isset($combined_by)) {
            $combined_by = self::COMB[strtoupper($combined_by)] ?? Sql::AND;
        }

        if (!isset($predicates)) {
            return;
        }

        if ($predicates instanceof PredicateSet) {
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
            if ($predicate instanceof PredicateSet) {
                $comb_by = self::COMB_ID[$key] ?? null;
                if (isset($comb_by) && $comb_by !== $predicate->getCombinedBy()) {
                    $nestedSet = new PredicateSet($comb_by, $predicate->getPredicates());
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
                    $nestedSet = new PredicateSet($comb_by, $predicate);
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
     * @param Predicate|string|array $predicate A Predicate/PredicateSet instance
     *      or a specs-array [identifier, operator, value] or [identifier => value]
     * @throws InvalidArgumentException
     */
    public function addPredicate($predicate)
    {
        if (is_string($predicate)) {
            $predicate = new Predicate\Literal($predicate);
        } elseif (is_array($predicate)) {
            $predicate = $this->buildPredicateFromSpecs($predicate);
        }

        if (! $predicate instanceof Predicate) {
            throw new InvalidArgumentException(sprintf(
                "Adding a predicate must be done using either as a string, a"
                . " Predicate/PredicateSet instance or an predicate specs-array such as "
                . "[identifier, operator, value] or [identifier => value], `%s` provided!",
                is_object($predicate) ? get_class($predicate) : gettype($predicate)
            ));
        }

        $this->predicates[] = $predicate;
        unset($this->sql); // remove rendered sql
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

        Sql::assertValidOperator($operator);
        $operator = strtoupper($operator);

        if (isset(Sql::COMPARISON_OPERATORS[$operator])) {
            return new Predicate\Comparison($identifier, $operator, $value);
        }

        if ($operator === Sql::BETWEEN) {
            return new Predicate\Between($identifier, $value);
        }
        if ($operator === Sql::NOT_BETWEEN) {
            return new Predicate\NotBetween($identifier, $value);
        }
        if ($operator === Sql::IN) {
            return new Predicate\In($identifier, $value);
        }
        if ($operator === Sql::NOT_IN) {
            return new Predicate\NotIn($identifier, $value);
        }
        if ($operator === Sql::LIKE) {
            return new Predicate\Like($identifier, $value);
        }
        if ($operator === Sql::NOT_LIKE) {
            return new Predicate\NotLike($identifier, $value);
        }

        if ($value instanceof Literal) {
            return new Predicate\Literal("{$identifier} {$operator} {$value}");
        }

        if (is_array($value)) {
             throw new InvalidArgumentException(
                "Array value not supported for operator `{$operator}`!"
            );
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

    public function getSQL(): string
    {
        if (isset($this->sql)) {
            return $this->sql;
        }

        $sqls = [];
        foreach ($this->predicates as $predicate) {
            $sql = $predicate->getSQL();
            if ($this->isEmptySQL($sql)) {
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

    public function jsonSerialize()
    {
        return [
            'class'  => static::class,
            'getSQL' => $this->getSQL(),
            'comby' => self::COMB[$this->combined_by],
        ];
    }
}
