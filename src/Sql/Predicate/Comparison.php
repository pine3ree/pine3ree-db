<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\Db\Sql\Predicate;

use pine3ree\Db\Exception\InvalidArgumentException;
use pine3ree\Db\Sql;
use pine3ree\Db\Sql\Alias;
use pine3ree\Db\Sql\Driver;
use pine3ree\Db\Sql\DriverInterface;
use pine3ree\Db\Sql\Identifier;
use pine3ree\Db\Sql\Literal;
use pine3ree\Db\Sql\Params;
use pine3ree\Db\Sql\Predicate;

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

    /**
     * @param string $operator
     *
     * @throws InvalidArgumentException
     */
    protected static function assertValidComparisonOperator(string $operator): void
    {
        if (!isset(Sql::COMPARISON_OPERATORS[$operator])) {
            throw new InvalidArgumentException(
                "Invalid comparison operator `{$operator}`, must be one of "
                . implode(', ', Sql::COMPARISON_OPERATORS) . "!"
            );
        }
    }

    /**
     * @param mixed $value
     *
     * @throws InvalidArgumentException
     */
    protected static function assertValidComparisonValue($value): void
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
            . " `%s` provided in class`%s`!",
            is_object($value) ? get_class($value) : gettype($value),
            static::class
        ));
    }

    public function getSQL(DriverInterface $driver = null, Params $params = null): string
    {
        if (isset($this->sql) && $driver === $this->driver && $params === null) {
            return $this->sql;
        }

        $this->driver = $driver; // set last used driver argument
        $this->params = null; // reset previously collected params, if any

        $driver = $driver ?? Driver::ansi();
        $params = $params ?? ($this->params = new Params());

        $identifier = $this->getIdentifierSQL($this->identifier, $driver);

        $operator = $this->operator;

        // Transform equality/inequality operators with null values into `IS NULL`,
        // `IS NOT NULL` expressions
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
            $param = $params->create($this->value, null, $this->createParamName($operator));
        }

        return $this->sql = "{$identifier} {$operator} {$param}";
    }

    private function createParamName(string $operator): string
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
