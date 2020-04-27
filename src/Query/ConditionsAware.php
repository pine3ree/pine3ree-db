<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Query;

use InvalidArgumentException;
use PDO;

use P3\Db\Query;
use P3\Db\Query\Expr;
use P3\Db\Query\Select;
use P3\Db\Sql;

/**
 * Class ConditionsAware
 */
abstract class ConditionsAware extends Query
{
    /** @var string|Expr|array|null */
    protected $where;

    /** @var string|Expr|array|null */
    protected $having;

    /** @var string|Expr|array|null */
    protected $on;

//    public const CLAUSE_WHERE  = 'WHERE';
//    public const CLAUSE_HAVING = 'HAVING';
//    public const CLAUSE_ON     = 'ON';
//
//    public const OP_BETWEEN     = 'BETWEEN';
//    public const OP_NOT_BETWEEN = 'NOT BETWEEN';
//    public const OP_IN          = 'IN';
//    public const OP_NOT_IN      = 'NOT IN';
//    public const OP_LIKE        = 'LIKE';
//    public const OP_NOT_LIKE    = 'NOT LIKE';
//
//    public const OP_AND = 'AND';
//    public const OP_OR  = 'OR';

    public const COMB_AND = '&&';
    public const COMB_OR  = '||';

    protected const COMBINED = [
        self::COMB_AND => Sql::AND,
        self::COMB_OR => Sql::OR,
    ];

    public function where($where): self
    {
        $this->where = $where;
        unset($this->sql, $this->sqls[Sql::WHERE]);
        return $this;
    }

    protected function getWhereSQL(): string
    {
        return $this->getClauseSQL(Sql::WHERE, $this->where);
    }

    public function having($having): self
    {
        $this->having = $having;
        unset($this->sql, $this->sqls[Sql::HAVING]);
        return $this;
    }

    protected function getHavingSQL(): string
    {
        return $this->getClauseSQL(Sql::WHERE, $this->having);
    }

    private function normalizeConditions($conditions): array
    {
        if (null === $conditions) {
            return [];
        }

        if (is_string($conditions) || $conditions instanceof Expr) {
            $sql = trim($conditions);
            return '' === $sql ? null : $sql;
        }

        if (!is_array($conditions)) {
            throw new InvalidArgumentException(sprintf(
                "The condition argument must be either a string, an Expr or an array, '%s' provided!",
                gettype($conditions)
            ));
        }

    }

    /**
     * Build the SQL string for a set of conditions
     *
     * @param string $type WHERE, HAVING, ON are the only accepted values
     * @param mixed|null|string|array|DbSqlComposite $conditions
     * @return string
     * @throws DbException
     */
    protected function getClauseSQL(string $clause, $conditions): string
    {
        if (!isset($conditions)
            || (is_array($conditions) && empty($conditions))
            || (is_string($conditions) && '' === $conditions)
        ) {
            return '';
        }

        $sql = $this->getConditionsSQL($conditions);

        if (is_null($sql)) {
            return '';
        }

        $sql = $this->stripParentheses($sql);

        return '' === $sql ? '' : strtoupper($clause) . " {$sql}";
    }

    /**
     * Resolve conditions to an SQL statement string and creating named parameters
     *
     * @param array|string|Expr $conditions
     * @param string $combined_by
     * @return string|null
     * @throws InvalidArgumentException
     */
    protected function getConditionsSQL($conditions, string $combined_by = self::COMB_AND): ?string
    {
        if (null === $conditions) {
            return null;
        }

        if (is_string($conditions) || $conditions instanceof Expr) {
            $sql = trim($conditions);
            return '' === $sql ? null : $sql;
        }

        if (!is_array($conditions)) {
            throw new InvalidArgumentException(sprintf(
                "The condition argument must be either a string, an Expr or an array, '%s' provided!",
                gettype($conditions)
            ));
        }

        $sqls = [];

        $i = 0;
        foreach ($conditions as $column => $value) {
            $i += 1;

            // numeric index
            if (is_numeric($column)) {
                // Direct SQL expression string
                if (is_string($value) || $value instanceof Expr) {
                    $sqls[] = trim($value);
                    continue;
                }

                // ['id', 'BETWEEN', [11, 22]]
                // ['id', 'IN', [11, 22, 33]]
                if (is_array($value)
                    && count($value) == 3
                ) {
                    $c = $this->quoteIdentifier($value[0]); // quoted column
                    $o = strtoupper($value[1]); // operator
                    $v = $value[2]; // value

                    switch ($o) {
                        case Sql::BETWEEN:
                        case Sql::NOT_BETWEEN:
                            $sqls[] = $this->getBetweenSQL($c, $o, $v);
                            break;

                        case Sql::IN:
                        case Sql::NOT_IN:
                            $sqls[] = $this->getInSQL($c, $o, $v);
                            break;

                        default:
                            $m = $this->createNamedParam($v); // marker
                            $sqls[] = "{$c} {$o} {$m}";
                            break;
                    }
                    continue;
                }

                throw new InvalidArgumentException(sprintf(
                    "The value of a condition with a numeric index must be either"
                    . " an sql string, an sql Expr or a 3-valued array with"
                    . " [identifier, operator, value] as elements, '%s' provided!",
                    gettype($value)
                ));
            }

            // Combined conditions using special strings "&&" or "||"
            if (isset(self::COMBINED[$column])) {
                $sqls[] = $this->getConditionsSQL($value, $column);
                continue;
            }

            if (is_array($value)) {
                $sqls[] = $this->getConditionsSQL($value, self::COMB_AND);
                continue;
            }

            $column = $this->quoteIdentifier($column);

            if (is_null($value)) {
                $marker = $this->createNamedParam($value, PDO::PARAM_NULL);
                $sqls[] = "{$column} IS {$marker}";
                continue;
            }

            // use PARAM_INT for boolean values as well
            // @see https://bugs.php.net/bug.php?id=38546
            if (is_bool($value) || is_int($value)) {
                $marker = $this->createNamedParam($value, PDO::PARAM_INT);
                $sqls[] = "{$column} = {$marker}";
                continue;
            }

            if (is_scalar($value)) {
                $marker = $this->createNamedParam($value, PDO::PARAM_STR);
                $sqls[] = "{$column} = {$marker}";
                continue;
            }

            throw new InvalidArgumentException(sprintf(
                "The value of a condition must be either a scalar, an Expr or an array, '%s' provided!",
                gettype($value)
            ));
        }

        // discard empty statements
        $sqls = array_filter($sqls, [$this, 'isNotEmptyStatement']);

        if (empty($sqls)) {
            return null;
        }

        if (1 === count($sqls)) {
            return $sqls[0];
        }

        $OP = self::COMBINED[$combined_by] ?? Sql::AND;

        return "(" . implode(" {$OP} ", $sqls) . ")";
    }

    protected function getBetweenSQL(string $identifier, string $operator, $values): string
    {
        if (!is_array($values) || count($values) !== 2) {
            throw new InvalidArgumentException(
                "The {$operator} operator requires a 2-item array value!"
            );
        }

        $marker_min = $this->createNamedParam($values[0]);
        $marker_max = $this->createNamedParam($values[1]);

        return  "{$identifier} {$operator} {$marker_min} AND {$marker_max}";
    }

    protected function getInSQL(string $identifier, string $operator, $values): string
    {
        if ($values instanceof Select) {
            $sub_sql    = $values->getSQL();
            $sub_params = $values->getParams();
            $sub_types  = $values->getParamsTypes();
            foreach ($sub_params as $index => $value) {
                $this->params[$index] = $value;
                $this->params_types[$index] = $sub_types[$index] ?? PDO::PARAM_STR;
            }
            return $this->isNotEmptyStatement($sub_sql)
                ? "{$identifier} {$operator} ({$sub_sql})"
                : "{$identifier} {$operator} (NULL)";
        }

        if (!is_array($values)) {
            throw new InvalidArgumentException(
                "The {$operator} operator requires an array of values or a sub-query!"
            );
        }

        if (empty($values)) {
            return "{$identifier} {$operator} (NULL)";
        }

        $in_values = [];
        $has_null = false;

        $i = 1;
        foreach ($values as $value) {
            if (is_null($value)) {
                $has_null = true;
            } else {
                $in_values[] = $this->createNamedParam($value);
            }
            $i += 1;
        }

        $in_value_list = "(" . implode(", ", $in_values) . ")";

        if ($has_null) {
            $marker_null = $this->createNamedParam($value, PDO::PARAM_NULL);
            if ($operator === Sql::NOT_IN ) {
                return "{$identifier} NOT IN {$in_value_list} AND {$identifier} IS NOT {$marker_null}";
            }
            return "{$identifier} IN {$in_value_list} OR {$identifier} IS {$marker_null}";
        }

        return  "{$identifier} {$operator} {$in_value_list}";
    }

    /**
     * Strip any surrounding matching pair of parentheses
     *
     * @param string $sql
     * @return bool
     */
    protected function stripParentheses(string $sql): string
    {
        //
        if ('(' === substr($sql, 0, 1) && substr($sql, -1) === ')') {
            return mb_substr($sql, 1, -1);
        }

        return $sql;
    }
}
