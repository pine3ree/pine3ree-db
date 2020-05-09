<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use InvalidArgumentException;
use P3\Db\Sql\Driver;
use P3\Db\Sql;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Predicate;

use function implode;
use function is_null;

/**
 * This class represents a sql comparison predicate for the following operators:
 * "=", "!=", "<>", "<", "<=", ">=", ">".
 */
class Comparison extends Predicate
{
    protected $identifier;
    protected $operator;
    protected $value;

    /**
     * @param string|Literal $identifier
     * @param string $operator
     * @param string|int|bool|Literal $value
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
        if (!isset(Sql::COMPARISON_OPERATORS[$operator])) {
            throw new InvalidArgumentException(
                "Invalid comparison operator `{$operator}`, must be one of "
                . implode(', ', Sql::COMPARISON_OPERATORS) . "!"
            );
        }
    }

    public function getSQL(Driver $driver = null): string
    {
        if (isset($this->sql)) {
            return $this->sql;
        }

        $driver = $driver ?? Driver::ansi();

        $identifier = $this->identifier instanceof Literal
            ? $this->identifier->getSQL()
            : $driver->quoteIdentifier($this->identifier);

        $param = $this->value instanceof Literal
            ? $this->value->getSQL()
            : $this->createNamedParam($this->value);

        $operator = $this->operator;
        if (is_null($this->value)) {
            switch ($operator) {
                case Sql::EQUAL:
                    $operator = "IS";
                    break;
                case Sql::NOT_EQUAL:
                case Sql::NOT_EQUAL_ANSI:
                    $operator = "IS NOT";
                    break;
            }
        }

        return $this->sql = "{$identifier} {$operator} {$param}";
    }
}
