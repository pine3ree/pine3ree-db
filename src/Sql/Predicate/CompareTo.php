<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use InvalidArgumentException;
use P3\Db\Sql;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Predicate;
use P3\Db\Sql\Statement\Select;

use function implode;

/**
 * This class represents a sql operator-ALL(SELECT...) condition
 */
abstract class CompareTo extends Predicate
{
    /** @var string|Literal */
    protected $identifier;

    /** @var string */
    protected $operator;

    /** @var Select */
    protected $select;

    /** @var string */
    protected static $quantifier;

    /**
     * @param string|Literal $identifier
     * @param string $operator
     * @param Select $select
     */
    public function __construct($identifier, string $operator, Select $select)
    {
        self::assertValidIdentifier($identifier);
        self::assertValidOperator($operator);

        $this->identifier = $identifier;
        $this->select = $select;
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

        $select_sql = $this->select->getSQL($driver);
        $this->importParams($this->select);

        $quantifier = self::$quantifier;

        return $this->sql = "{$identifier} {$operator} {$quantifier}({$select_sql})";
    }
}
