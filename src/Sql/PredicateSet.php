<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use InvalidArgumentException;
use P3\Db\Sql;
use P3\Db\Sql\Predicate;
use P3\Db\Sql\Predicate\Literal;

/**
 * Class PredicateSet
 */
class PredicateSet extends Predicate
{
    protected $predicates = [];
    protected $combined_by;

    public const COMB_AND = '&&';
    public const COMB_OR  = '||';

    protected const COMB = [
        self::COMB_AND => Sql::AND,
        self::COMB_OR  => Sql::OR,
    ];

    public function __construct(array $predicates = null, string $comb_by = self::COMB_AND)
    {
        foreach ($predicates as $predicate) {
            $this->addPredicate($predicate);
        }

        $this->combined_by = isset(self::COMB[$comb_by]) ? $comb_by : self::COMB_AND;
    }

    /**
     *
     * @param Predicate|string $predicate
     * @throws InvalidArgumentException
     */
    public function addPredicate($predicate)
    {
        if (is_string($predicate)) {
            $predicate = new Literal($predicate);
        } elseif (is_array($predicate) && 3 === count($predicate)) {
            $predicate = $this->buildPredicate($predicate);
        }
        if (! $predicate instanceof Predicate) {
            throw new InvalidArgumentException(sprintf(
                "A predicates array element can be defined either as a string,"
                . " a Predicate instance or an [identifier, operator, value] array"
                . " expression!, `%s` provided",
                is_object($predicate) ? get_class($predicate) : gettype($predicate)
            ));
        }
        if ($predicate instanceof Predicate) {
            $this->predicates[] = $predicate;
        }
    }

    protected function buildPredicate(array $array)
    {
        $identifier = $array[0];
        $operator   = $array[1];
        $value      = $array[2];
    }

    /**
     * @return Predicate[]
     */
    public function getPredicates(): array
    {
        return $this->predicates;
    }

    public function getSQL(): string
    {
        $sqls = [];
        foreach ($this->predicates as $predicate) {
            $sqls[] = trim($predicate->getSQL());
        }

        $sqls = array_filter($sqls, function ($sql) {
            return '' !== $sql;
        });

        if (empty($sqls)) {
            return '';
        }

        if (1 === count($sqls)) {
            return $sqls[0];
        }

        $LOGICAL_OP = self::COMB[$this->combined_by];

        return "(" . trim(implode(" {$LOGICAL_OP} ", $sqls)) . ")";
    }

    /**
     * Strip any surrounding matching pair of parentheses
     *
     * @param string $sql
     * @return bool
     */
    protected function stripParentheses(string $sql): string
    {
        if ('(' === substr($sql, 0, 1) && substr($sql, -1) === ')') {
            return mb_substr($sql, 1, -1);
        }

        return $sql;
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
