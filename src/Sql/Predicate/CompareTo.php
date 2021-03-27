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
use P3\Db\Sql\Statement\Select;

use function implode;

/**
 * This class represents a sql operator-ALL/ANY/SOME(SELECT...) condition
 *
 * @property-read Select $select The sql select statement this predicate refers to
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

        $this->select = $select->parentIsNot($this) ? clone $select : $select;
        $this->select->parent = $this;
    }

    /**
     * @param string $operator
     * @return void
     * @throws InvalidArgumentException
     */
    private static function assertValidComparisonOperator(string $operator)
    {
        if (!isset(Sql::COMPARISON_OPERATORS[$operator])) {
            throw new InvalidArgumentException(
                "Invalid comparison operator `{$operator}`, must be one of "
                . implode(', ', Sql::COMPARISON_OPERATORS) . "!"
            );
        }
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
        $select_sql = $this->select->getSQL($driver, $params);
        $quantifier = static::$quantifier;

        return $this->sql = "{$identifier} {$this->operator} {$quantifier} ({$select_sql})";
    }

    /**
     * @return mixed
     */
    public function __get(string $name)
    {
        if ('select' === $name) {
            return $this->select;
        }

        return parent::__get($name);
    }

    public function __clone()
    {
        parent::__clone();
        $this->select = clone $this->select;
        $this->select->parent = $this;
    }
}
