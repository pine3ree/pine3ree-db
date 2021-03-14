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
    /** @var string|Alias|Identifier|Literal */
    protected $identifier;

    /** @var bool|null|string */
    protected $value;

    /** @var bool */
    protected static $not = false;

    /**
     * @param string|Alias|Identifier|Literal $identifier
     * @param bool|null|string $value
     */
    public function __construct($identifier, $value)
    {
        self::assertValidIdentifier($identifier);

        $this->identifier = $identifier;

        if (is_bool($value) || is_null($value) || $value === Sql::UNKNOWN) {
            $this->value = $value;
        } elseif (is_string($value)) {
            $this->value = self::validateAndNormalizeStringValue($value);
        } else {
            self::throwInvalidArgumentException($value);
        }
    }

    /**
     * @param string $value
     * @return bool|null|string
     * @throws InvalidArgumentException
     */
    private static function validateAndNormalizeStringValue(string $value)
    {
        $ucvalue = strtoupper($value);

        // accepts the string TRUE => convert to bool true
        if ($ucvalue === 'TRUE') {
            return true;
        }

        // accepts the string FALSE => convert to bool false
        if ($ucvalue === 'FALSE') {
            return false;
        }

        // accepts the string NULL => convert to null
        if ($ucvalue === 'NULL') {
            return null;
        }

        // accepts the string UNKNOWN => convert to null
        if ($ucvalue === Sql::UNKNOWN) {
            return Sql::UNKNOWN;
        }

        self::throwInvalidArgumentException($value);
        // @codeCoverageIgnoreStart
    }
    // @codeCoverageIgnoreEnd

    protected static function throwInvalidArgumentException($value)
    {
        throw new InvalidArgumentException(sprintf(
            "The IS-value, must be one of:"
            . " `null`, `true`, `false` or the ci-strings 'NULL', 'TRUE', 'FALSE', 'UNKNOWN',"
            . " %s provided!",
            is_string($value) ? "`{$value}`" : "`" . gettype($value) . "` type"
        ));
    }

    public function getSQL(DriverInterface $driver = null, Params $params = null): string
    {
        if (isset($this->sql) && $params === null) {
            return $this->sql;
        }

        $this->params = null; // reset previously collected params, if any

        $driver = $driver ?? Driver::ansi();
        $params = $params ?? ($this->params = new Params());

        $identifier = $this->getIdentifierSQL($this->identifier, $driver);

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
