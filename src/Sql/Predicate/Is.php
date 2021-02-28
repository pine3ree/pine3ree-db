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

use function gettype;
use function is_bool;
use function is_null;
use function is_string;
use function sprintf;
use function strtoupper;

/**
 * This class represents a sql IS predicate with the SQL values NULL, TRUE,
 * FALSE and UNKNOWN
 */
class Is extends Predicate
{
    /** @var string|Literal */
    protected $identifier;

    /** @var bool|null|string */
    protected $value;

    /** @var bool */
    protected static $not = false;

    /**
     * @param string|Literal $identifier
     * @param string $operator
     * @param bool|null|string $value
     */
    public function __construct($identifier, $value)
    {
        self::assertValidIdentifier($identifier);

        $this->identifier = $identifier;
        $this->value = self::validateAndNormalizeValue($value);
    }

    /**
     * @param mixed $value
     * @return bool|null|string
     * @throws InvalidArgumentException
     */
    private static function validateAndNormalizeValue($value)
    {
        if (is_bool($value) || is_null($value) || $value === Sql::UNKNOWN) {
            return $value;
        }

        if (is_string($value)) {
            $value = strtoupper($value);
        }

        // accepts the string TRUE => convert to bool true
        if ($value === 'TRUE') {
            return true;
        }

        // accepts the string FALSE => convert to bool false
        if ($value === 'FALSE') {
            return false;
        }

        // accepts the string NULL => convert to null
        if ($value === 'NULL') {
            return null;
        }

        throw new InvalidArgumentException(sprintf(
            "The boolean operator `{$operator}` comparison value must be one of"
            . " `null`, `true`, `false` or the strings 'NUUL', 'TRUE', 'FALSE', 'UNKNOWN', "
            . "`%s` provided!",
            is_string($value) ? $value : gettype($value)
        ));
    }

    public function getSQL(Driver $driver = null): string
    {
        if (isset($this->sql)) {
            return $this->sql;
        }

        $driver = $driver ?? Driver::ansi();

        $identifier = $this->identifier instanceof Literal
            ? $this->identifier->getSQL($driver)
            : $driver->quoteIdentifier($this->identifier);

        if ($this->value === null) {
            $param = Sql::NULL;
        } elseif ($this->value === true) {
            $param = Sql::TRUE;
        } elseif ($this->value === false) {
            $param = Sql::FALSE;
        } else {
            $param = Sql::UNKNOWN;
        }

        $operator = static::$not ? Sql::IS_NOT : Sql::IS;

        return $this->sql = "{$identifier} {$operator} {$param}";
    }
}
