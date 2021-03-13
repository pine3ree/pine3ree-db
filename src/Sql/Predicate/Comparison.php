<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use P3\Db\Exception\InvalidArgumentException;
use P3\Db\Sql;
use P3\Db\Sql\Alias;
use P3\Db\Sql\Driver;
use P3\Db\Sql\DriverInterface;
use P3\Db\Sql\Identifier;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Params;
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
    /** @var string|Alias|Identifier|Literal */
    protected $identifier;

    /** @var string */
    protected $operator;

    /** @var scalar|Literal|Identifier|Alias|null */
    protected $value;

    /**
     * @var int custom index counter
     */
    protected static $index = 0;

    /**
     * @param string|Alias|Identifier|Literal $identifier
     * @param string $operator
     * @param scalar|Literal|Identifier|Alias|null $value
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
            || $value === null
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

    public function getSQL(DriverInterface $driver = null, Params $params = null): string
    {
        if (isset($this->sql) && $params === null) {
            return $this->sql;
        }

        $this->resetParams();

        $driver = $driver ?? Driver::ansi();
        $params = $params ?? ($this->params = new Params());

        $identifier = $this->getIdentifierSQL($this->identifier, $driver);

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
            $param = $params->createParam($this->value, null, $this->getParamName($operator));
        }

        return $this->sql = "{$identifier} {$operator} {$param}";
    }

    private function getParamName(string $operator): string
    {
        static $map = [
            '='  => 'eq',
            '!=' => 'neq',
            '<>' => 'ne',
            '<'  => 'lt',
            '<=' => 'lte',
            '>=' => 'gte',
            '>'  => 'gt',
        ];

        return $map[$operator] ?? 'comp';
    }
}
