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
use P3\Db\Sql\Predicate;
use P3\Db\Sql\Statement\Select;

use function implode;

/**
 * This class represents a sql operator-ALL/ANY/SOME(SELECT...) condition
 */
abstract class CompareTo extends Predicate
{
    /** @var string|Alias|Identifier|Literal */
    protected $identifier;

    /** @var string */
    protected $operator;

    /** @var Select */
    protected $select;

    /** @var string */
    protected static $quantifier;

    /**
     * @param string|Alias|Identifier|Literal $identifier
     * @param string $operator
     * @param Select $select
     */
    public function __construct($identifier, string $operator, Select $select)
    {
        self::assertValidIdentifier($identifier);
        self::assertValidComparisonOperator($operator);

        $this->identifier = $identifier;
        $this->operator = $operator;
        $this->select = $select;
    }

    private static function assertValidComparisonOperator(string $operator)
    {
        if (!isset(Sql::COMPARISON_OPERATORS[$operator])) {
            throw new InvalidArgumentException(
                "Invalid comparison operator `{$operator}`, must be one of "
                . implode(', ', Sql::COMPARISON_OPERATORS) . "!"
            );
        }
    }

    public function getSQL(DriverInterface $driver = null): string
    {
        if (isset($this->sql)) {
            return $this->sql;
        }

        $this->resetParams();

        $driver = $driver ?? Driver::ansi();

        $identifier = self::quoteGenericIdentifier($this->identifier, $driver);

        $select_sql = $this->select->getSQL($driver);
        $this->importParams($this->select);

        $quantifier = static::$quantifier;

        return $this->sql = "{$identifier} {$this->operator} {$quantifier}({$select_sql})";
    }
}
