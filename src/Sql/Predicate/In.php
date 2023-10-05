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

use function get_class;
use function gettype;
use function implode;
use function is_array;
use function is_object;
use function sprintf;

/**
 * This class represents a sql IN condition
 *
 * @property-read array|Select $valueList The IN value-list
 */
class In extends Predicate
{
    /** @var string|Alias|Identifier|Literal */
    protected $identifier;

    /** @var array|Select */
    protected $valueList;

    /** @var bool */
    protected static $not = false;

    /**
     * @param string|Alias|Identifier|Literal $identifier
     * @param array|Select $valueList
     */
    public function __construct($identifier, $valueList)
    {
        self::assertValidIdentifier($identifier);
        self::assertValidValueList($valueList);

        $this->identifier = $identifier;

        if ($valueList instanceof Select) {
            if ($valueList->parentIsNot($this)) {
                $valueList = clone $valueList;
            }
            $valueList->parent = $this;
        }

        $this->valueList = $valueList;
    }

    /**
     * @param mixed $valueList
     * @return void
     * @throws InvalidArgumentException
     */
    protected static function assertValidValueList($valueList)
    {
        if (!is_array($valueList) && ! $valueList instanceof Select) {
            throw new InvalidArgumentException(sprintf(
                "A IN/NOT-IN predicate value list must be either an array of values"
                . " or a Select statement, '%s' provided!",
                is_object($valueList) ? get_class($valueList) : gettype($valueList)
            ));
        }

        if (is_array($valueList)) {
            foreach ($valueList as $value) {
                self::assertValidValue($value);
            }
        }
    }

    /**
     * If one of the values is NULL then add an OR/AND IS NULL clause
     */
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

        $operator = static::$not ? Sql::NOT_IN : Sql::IN;

        if ($this->valueList instanceof Select) {
            $select_sql = $this->valueList->getSQL($driver, $params);
            return $this->sql = "{$identifier} {$operator} ({$select_sql})";
        }

        $values = [];
        $hasNull = false;
        foreach ($this->valueList as $value) {
            if (null === $value) {
                $hasNull = true;
                continue;
            }
            $values[] = $this->getValueSQL($params, $value, null, 'in');
        }

        $ivl_sql = "(" . (empty($values) ? Sql::NULL : implode(", ", $values)) . ")";

        $null_sql = "";
        if ($hasNull) {
            $null_sql = " " . (
                static::$not
                ? Sql::AND . " {$identifier} " . Sql::IS_NOT . " " . Sql::NULL
                : Sql::OR . " {$identifier} " . Sql::IS . " " . Sql::NULL
            );
        }

        $sql = "{$identifier} {$operator} {$ivl_sql}{$null_sql}";
        if ($hasNull) {
            $sql = "({$sql})";
        }

        return $this->sql = $sql;
    }

    /**
     * @return mixed
     */
    public function __get(string $name)
    {
        if ('valueList' === $name) {
            return $this->valueList;
        }

        return parent::__get($name);
    }

    public function __clone()
    {
        parent::__clone();
        if ($this->valueList instanceof Select) {
            $this->valueList = clone $this->valueList;
            $this->valueList->parent = $this;
        }
    }
}
