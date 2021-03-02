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
 *
 * It also intercepts equality/inequality with boolean/null values using IS/IS NOT
 * operators with SQL values TERUE, FALSE, and NULL.
 */
class Comparison extends Predicate
{
    /** @var string|Literal */
    protected $identifier;

    /** @var string */
    protected $operator;

    /** @var string|int|bool|Literal */
    protected $value;

    /**
     * @var int custom index counter
     */
    protected static $index = 0;

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

        $this->resetParams();

        $driver = $driver ?? Driver::ansi();

        $identifier = $this->quoteIdentifier($this->identifier, $driver);

        $operator = $this->operator;

        // transform equality/inequality operators with null values into `IS NULL`,
        //  `IS NOT NULL` expressions
        if (is_null($this->value)) {
            switch ($operator) {
                case Sql::EQUAL:
                    $operator = Sql::IS;
                    break;
                case Sql::NOT_EQUAL:
                case Sql::NOT_EQUAL_ANSI:
                    $operator = Sql::IS_NOT;
                    break;
                default:
                    throw new InvalidArgumentException(
                        "Invalid operator `{$operator}` for null value, must be one of "
                        . implode(', ', Sql::BOOLEAN_OPERATORS) . "!"
                    );
            }
            $param = Sql::NULL;
        } else {
            $min = $this->createSqlForValue($this->value, null, $this->getParameterName($operator));
        }

        return $this->sql = "{$identifier} {$operator} {$param}";
    }

    private function getParameterName(string $operator): ?string
    {
        if ('=' === $operator) {
            return 'eq';
        }
        if ('!=' === $operator) {
            return 'neq';
        }
        if ('<>' === $operator) {
            return 'ne';
        }
        if ('<' === $operator) {
            return 'lt';
        }
        if ('<=' === $operator) {
            return 'lte';
        }
        if ('>=' === $operator) {
            return 'gte';
        }
        if ('>' === $operator) {
            return 'gt';
        }

        return null;
    }
}
