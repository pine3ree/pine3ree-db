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
use pine3ree\Db\Sql\Statement\Select;

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
     *
     * @throws InvalidArgumentException
     */
    private static function assertValidComparisonOperator(string $operator): void
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
        if ($this->hasValidSqlCache($driver, $params)) {
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
