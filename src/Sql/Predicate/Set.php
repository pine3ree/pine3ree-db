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

use function array_map;
use function array_values;
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
        'notEqual'    => Sql::NOT_EQUAL,
        'notBetween'  => Sql::NOT_BETWEEN,
        'notIn'       => Sql::NOT_IN,
        'notLike'     => Sql::NOT_LIKE,
        'regExpCs'    => Sql::REGEXP_CS,
        'notRegExp'   => Sql::NOT_REGEXP,
        'notRegExpCs' => Sql::NOT_REGEXP_CS,
    ];

    /**
     * Aliases ("||" for "OR" and "&&" for "AND") for nested-predicates array
     * definitons
     *
     * @see ConditionalClauseAwareTrait::setConditionalClause()
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
     *      ['||' => [
     *          'status' => [
     *              ['=', null], // `status` IS NULL
     *              ['BETWEEN', [2, 16]], // OR `status` BETWEEN '2' AND '16'
     *          ],
     *          new Predicate\Literal("created_at <= '2019-12-31'"), // OR `created_at` <= '2020-01-01'
     *      ]].
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
                $this->addPredicate($predicate);
                continue;
            }

            if (is_numeric($key)) {
                $this->addPredicate($predicate);
                continue;
            }

            // $key is an identifier and the array may be a predicate-building
            // spec in the form [operator, value] that allows to set multiple
            // conditions for a single identifier
            if (is_array($predicate)) {
                foreach ($predicate as $specs) {
                    if (is_array($specs) && 2 === count($specs)) {
                        $specs = array_values($specs);
                        $this->addPredicate($specs = [$key, $specs[0], $specs[1]]);
                        continue;
                    }
                    throw new InvalidArgumentException(sprintf(
                        "Invalid predicate-specs for key/identifier `{$key}`, the"
                        . " allowed form is [operator, value], `%s` provided!",
                        '[' . array_map('gettype', $specs) . ']'
                    ));
                }
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

        $this->predicates[] = $predicate;
        $this->sql = null; // remove rendered sql

        return $this;
    }

    protected function buildPredicateFromSpecs(array $specs): Predicate
    {
        if (count($specs) === 1) {
            $key = key($specs);
            if (!is_numeric($key)) {
                $comb_by = self::COMB_ID[$key] ?? null;
                if (isset($comb_by)) {
                    return new self($comb_by, current($specs));
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

        $operator = self::OPERATOR_ALIAS[$operator] ?? strtoupper($operator);
        Sql::assertValidOperator($operator);

        if (isset(Sql::COMPARISON_OPERATORS[$operator])) {
            return new Predicate\Comparison($identifier, $operator, $value);
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

        if (empty($this->predicates)) {
            return $this->sql = '';
        }

        $driver = $driver ?? Driver::ansi();

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
}
