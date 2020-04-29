<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use InvalidArgumentException;
use P3\Db\Sql;
use P3\Db\Sql\Expression;
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

    public const COMB_ID = [
        self::COMB_AND => self::COMB_AND,
        self::COMB_OR  => self::COMB_OR,
    ];

    /**
     * @param string $combined_by
     * @param null|Predicates[]|PredicateSet|Predicate|array|string $predicates
     */
    public function __construct(string $combined_by = Sql::AND, $predicates = null)
    {
        $this->combined_by = self::COMB[strtoupper($combined_by)] ?? Sql::AND;

        if (!isset($predicates)) {
            return;
        }

        if ($predicates instanceof PredicateSet) {
            $this->predicates = $predicates->getPredicates();
            return;
        }

        if (is_array($predicates)) {
            foreach ($predicates as $key => $predicate) {
                if ($predicate instanceof PredicateSet) {
                    $comb_by = self::COMB[strtoupper($key)] ?? $predicate->getCombinedBy();
                    $predicate = new PredicateSet($comb_by, $predicate->getPredicates());
                } elseif (!is_numeric($key) && ! $predicate instanceof Predicate) {
                    $predicate = new Predicate\Comparison($key, '=', $predicate);
                }
                $this->addPredicate($predicate);
            }
            return;
        }

        $this->addPredicate($predicates);
    }

    /**
     *
     * @param Predicate|string|array $predicate
     * @throws InvalidArgumentException
     */
    public function addPredicate($predicate, array $params = null)
    {
        if (is_string($predicate)) {
            $predicate = empty($params)
                ? new Predicate\Literal($predicate)
                : new Predicate\Expression($predicate, $params);
        } elseif (is_array($predicate)) {
            $predicate = $this->buildPredicateFromSpecs($predicate);
        }

        if (! $predicate instanceof Predicate) {
            throw new InvalidArgumentException(sprintf(
                "A single predicate must be defined either as a string, a"
                . " Predicate/PredicateSet instance or an specs-array such as "
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
                "A predicate single value specs-array must have a non-numeric string key, `%s`  provided",
                $key
            ));
        }

        if (count($specs) !== 3) {
            throw new InvalidArgumentException(
                "A predicate specs-array mus t be provide in one of the following forms"
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

        switch ($operator) {
            case Sql::BETWEEN:
                return new Predicate\Between($identifier, $value);
                // break;
            case Sql::NOT_BETWEEN:
                return new Predicate\NotBetween($identifier, $value);
                // break;
            case Sql::IN:
                return new Predicate\In($identifier, $value);
                // break;
            case Sql::NOT_IN:
                return new Predicate\NotIn($identifier, $value);
                // break;
            case Sql::LIKE:
                return new Predicate\Like($identifier, $value);
                // break;
            case Sql::NOT_LIKE:
                return new Predicate\NotLike($identifier, $value);
                // break;
        }

        if ($value instanceof Literal) {
            return new Predicate\Literal("{$identifier} {$operator} {$value}");
        }

        if (is_array($value)) {
             throw new InvalidArgumentException(
                "Array value not supported for operator `{$operator}`"
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
            $sql = trim($predicate->getSQL());
            if ($this->isEmptySQL($sql)) {
                continue;
            }
            $sqls[] = $sql;
            $this->importParams($predicate);
        }

        $sqls = array_filter($sqls, function ($sql) {
            return '' !== $sql;
        });

        if (empty($sqls)) {
            return $this->sql = '';
        }

        if (1 === count($sqls)) {
            return $this->sql = $sqls[0];
        }

        $LOGICAL_OP = self::COMB[$this->combined_by];

        return $this->sql = "(" . trim(implode(" {$LOGICAL_OP} ", $sqls)) . ")";
    }

    /**
     * Import parameters and types from inner predicate
     *
     * @param Predicate $predicate
     */
    private function importParams(Predicate $predicate)
    {
        foreach ($predicate->getParams() as $index => $value) {
            $this->params[$index] = $value;
        }
        foreach ($predicate->getParamsTypes() as $index => $type) {
            $this->params_types[$index] = $type;
        }
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
