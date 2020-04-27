<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Query\Clause;

use InvalidArgumentException;
use PDO;

use P3\Db\Query\Clause;
use P3\Db\Query\Expr;
use P3\Db\Query\Select;

/**
 * Class Conditions
 */
class Conditions extends Clause
{
//   use ParamsAwareTrait;

    private $conditions;

    /** @var &array */
    private $params;

    /** @var &array */
    private $params_types;

    public const OP_BETWEEN     = 'BETWEEN ';
    public const OP_NOT_BETWEEN = 'NOT BETWEEN';
    public const OP_IN          = 'IN';
    public const OP_NOT_IN      = 'NOT IN';
    public const OP_LIKE        = 'LIKE';
    public const OP_NOT_LIKE    = 'NOT LIKE';

    public const OP_AND = 'AND';
    public const OP_OR  = 'OR';

    public const COMB_AND = '&&';
    public const COMB_OR  = '||';

    private const COMBINED = [
        self::COMB_AND => self::OP_AND,
        self::COMB_OR => self::OP_OR,
    ];

    public function __construct($conditions, array &$params = null, array &$params_types = null)
    {
        $this->conditions = $conditions;
        $this->params = &$params;
        $this->params_types = &$params_types;
    }

    /**
     * Build the SQL string for a set of conditions
     *
     * @param string $type WHERE, HAVING, ON are the only accepted values
     * @param mixed|null|string|array|DbSqlComposite $conditions
     * @return string|null
     * @throws DbException
     */
    public function getSQL(): ?string
    {
        $sql = $this->getConditionsSQL($this->conditions);

        if (is_null($sql)) {
            return null;
        }

        $sql = $this->stripParentheses($sql);

        return '' === $sql ? null : $sql;
    }

    /**
     * Resolve conditions to an SQL statement string and creating named parameters
     *
     * @param array|string|Expr $conditions
     * @param string $combined_by
     * @return string|null
     * @throws InvalidArgumentException
     */
    private function getConditionsSQL($conditions, string $combined_by = self::COMB_AND): ?string
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
                        case self::OP_BETWEEN:
                        case self::OP_NOT_BETWEEN:
                            $sqls[] = $this->getBetweenSQL($c, $o, $v);
                            break;

                        case self::OP_IN:
                        case self::OP_NOT_IN:
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

            if (is_bool($value) || is_int($value)) {
                // use PARAM_INT for boolean values as well
                // @see https://bugs.php.net/bug.php?id=38546
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

        $OP = self::COMBINED[$combined_by] ?? self::OP_AND;

        return "(" . implode(" {$OP} ", $sqls) . ")";
    }

    private function getBetweenSQL(string $identifier, string $operator, $values): string
    {
        if (!is_array($values) || count($values) !== 2) {
            throw new InvalidArgumentException(
                "The {$operator} operator requires a 2-item array value!"
            );
        }

        $marker_min = $this->createNamedParam($v[0]);
        $marker_max = $this->createNamedParam($v[1]);

        return  "{$identifier} {$operator} {$marker_min} AND {$marker_max}";
    }

    private function getInSQL(string $identifier, string $operator, $values): string
    {
        if ($values instanceof Select) {
            $sub_sql = $values->getSQL();
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
            if ($operator === self::OP_NOT_IN ) {
                return "{$identifier} NOT IN {$in_value_list} AND {$identifier} IS NOT {$marker_null}";
            }
            return "{$identifier} IN {$in_value_list} OR {$identifier} IS {$marker_null}";
        }

        return  "{$identifier} {$operator} {$in_value_list}";
    }

    private function quoteIdentifier(string $identifier): string
    {
        $identifier = trim($identifier, '.`');
        if (false === strpos($identifier, '.')) {
            return "`{$identifier}`";
        }

        return "`" . str_replace(".", "`.`", $identifier) . "`";
    }

    private function createNamedParam($value, int $param_type = null): string
    {
        static $i = 1;

        $marker = ":v{$i}";

        $this->params[$marker] = $value;

        if (!isset($param_type)) {
            if (is_null($value)) {
                $param_type = PDO::PARAM_NULL;
            } elseif (is_int($value) || is_bool($value)) {
                $param_type = PDO::PARAM_INT;
            } else {
                $param_type = PDO::PARAM_STR;
            }
        }

        $this->params_types[$marker] = $param_type;

        $i = $i < 999 ? ($i + 1) : 1;

        return $marker;
    }

    private function isNotEmptyStatement($sql): bool
    {
        return isset($sql) && is_string($sql) && '' !== $sql;
    }

    /**
     * Strip any surrounding matching pair of parentheses
     *
     * @param string $sql
     * @return bool
     */
    private function stripParentheses(string $sql): string
    {
        //
        if ('(' === substr($sql, 0, 1) && substr($sql, -1) === ')') {
            return mb_substr($sql, 1, -1);
        }

        return $sql;
    }
}
