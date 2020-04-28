<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use InvalidApplicationNameException;
use P3\Db\Sql;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Predicate;

/**
 * This class represents a sql comparison condition
 */
class Comparison extends Predicate
{
    protected $identifier;
    protected $operator;
    protected $value;

    private const OPERATORS = [
        Sql::EQ  => Sql::EQ,
        Sql::NEQ => Sql::NEQ,
        Sql::NE  => Sql::NE,
        Sql::LT  => Sql::LT,
        Sql::LTE => Sql::LTE,
        Sql::GTE => Sql::GTE,
        Sql::GT  => Sql::GT,
    ];

    /**
     * @param string|Literal $identifier
     * @param scalar|literal|array<int, scalar|literal> $min_value
     * @param scalar|literal $max_value
     */
    public function __construct($identifier, string $operator, $value)
    {
        self::assertValidIdentifier($identifier);
        self::assertValidOperator($operator);

        $this->identifier = $identifier;
        $this->operator = $operator;
        $this->value = $value;
    }

    private static function assertValidOperator(string $operator)
    {
        if (!isset(self::OPERATORS[$operator])) {
            throw new InvalidApplicationNameException(
                "Invalid comparison operator `{$identifier}`, must be one of "
                . implode(', ', self::OPERATORS) . "!"
            );
        }
    }

    public function getSQL(): string
    {
        if (isset($this->sql)) {
            return $this->sql;
        }

        $identifier = $this->identifier instanceof Literal
            ? (string)$this->identifier
            : $this->quoteIdentifier($this->identifier);

        $param = $this->value instanceof Literal
            ? (string)$this->value
            : $this->createNamedParam($this->value);

        $operator = $this->operator;
        if (is_null($this->value)) {
            switch ($operator) {
                case Sql::EQ:
                    $operator = "IS";
                    break;
                case Sql::NEQ:
                case Sql::NE:
                    $operator = "IS NOT";
                    break;
            }
        }

        return $this->sql = "{$identifier} {$operator} {$param}";
    }
}
