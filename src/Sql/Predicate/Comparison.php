<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use P3\Db\Exception\InvalidArgumentException;
use P3\Db\Sql\Driver;
use P3\Db\Sql;
use P3\Db\Sql\Alias;
use P3\Db\Sql\Identifier;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Predicate;

use function get_class;
use function gettype;
use function implode;
use function is_null;
use function is_object;
use function sprintf;

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
        self::assertValidComparisonOperator($operator);
        self::assertValidComparisonValue($value);

        $this->identifier = $identifier;
        $this->operator = $operator;
        $this->value = $value;
    }

    protected static function assertValidComparisonOperator(string $operator)
    {
        if (!isset(Sql::COMPARISON_OPERATORS[$operator])) {
            throw new InvalidArgumentException(
                "Invalid comparison operator `{$operator}`, must be one of "
                . implode(', ', Sql::COMPARISON_OPERATORS) . "!"
            );
        }
    }

    protected static function assertValidComparisonValue($value)
    {
        if (is_scalar($value)
            || null === $value
            || $value instanceof Literal
            || $value instanceof Identifier
            || $value instanceof Alias
        ) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            "A comparison-predicate value must be either"
            . " a scalar,"
            . " null,"
            . " a SQL-literal,"
            . " a SQL-alias or"
            . " a SQL-identifier,"
            . " `%s` provided in class``%s!",
            is_object($value) ? get_class($value) : gettype($value),
            static::class
        ));
    }

    public function getSQL(Driver $driver = null): string
    {
        if (isset($this->sql)) {
            return $this->sql;
        }

        $this->resetParams();

        $driver = $driver ?? Driver::ansi();

        $identifier = self::quoteGenericIdentifier($this->identifier, $driver);

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
        } elseif ($this->value instanceof Literal
            || $this->value instanceof Identifier
            || $this->value instanceof Alias
        ) {
            $param = $this->value->getSQL($driver);
        } else {
            $param = $this->createParam($this->value, null, $this->getParamName($operator));
        }

        return $this->sql = "{$identifier} {$operator} {$param}";
    }

    private function getParamName(string $operator): string
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

        // @codeCoverageIgnoreStart
        return 'comp';
        // @codeCoverageIgnoreEnd
    }
}
