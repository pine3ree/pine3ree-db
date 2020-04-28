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
use P3\Db\Sql\Predicate\Expression as ExpressionPredicate;
use P3\Db\Sql\Predicate\Literal as LiteralPredicate;

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
     *
     * @param string $combined_by
     * @param null|Predicates[]|Predicate|string $predicates
     */
    public function __construct(string $combined_by = Sql::AND, $predicates = null)
    {
        $this->combined_by = self::COMB[strtoupper($combined_by)] ?? Sql::AND;

        if (isset($predicates)) {
            if (is_array($predicates)) {
                foreach ($predicates as $predicate) {
                    $this->addPredicate($predicate);
                }
            } elseif ($predicates instanceof PredicateSet) {
                $this->predicates = $predicates->getPredicates();
            } else {
                $this->addPredicate($predicates);
            }
        }
    }

    /**
     *
     * @param Predicate|string $predicate
     * @throws InvalidArgumentException
     */
    public function addPredicate($predicate, array $params = null)
    {
        if (is_string($predicate)) {
            $predicate = empty($params)
                ? new LiteralPredicate($predicate)
                : new ExpressionPredicate($predicate, $params);
        } elseif (is_array($predicate) && count($predicate) === 3) {
            $predicate = $this->buildPredicateFromSpecs($predicate);
        }

        if (! $predicate instanceof Predicate) {
            throw new InvalidArgumentException(sprintf(
                "A single predicate must be defined either as a string, a"
                . " Predicate instance or an [identifier, operator, value] array"
                . " expression, `%s` provided!",
                is_object($predicate) ? get_class($predicate) : gettype($predicate)
            ));
        }

        $this->predicates[] = $predicate;
        unset($this->sql); // remove rendered sql
    }

    protected function buildPredicateFromSpecs(array $specs): Predicate
    {
        $identifier = $specs[0]; // identifier or Literal sql expression
        $operator   = $specs[1];
        $value      = $specs[2];

        Sql::assertValidOperator($operator);

        if ($value instanceof Literal) {
            return new LiteralPredicate("{$identifier} {$operator} {$value}");
        }

        $params = [];
        if (is_array($value)) {
            foreach ($value as $v) {
                $marker = $this->createNamedParam($v);
                $params[$marker] = $v;
            }
        } else {
            $marker = $this->createNamedParam($value);
            $params[$marker] = $value;
        }

        return new ExpressionPredicate("{$identifier} {$operator} {$marker}", $params);
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
