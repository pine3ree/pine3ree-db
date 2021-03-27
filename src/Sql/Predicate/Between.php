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

/**
 * This class represents a sql BETWEEN condition
 */
class Between extends Predicate
{
    /** @var string|Alias|Identifier|Literal */
    protected $identifier;

    /** @var mixed */
    protected $minValue;

    /** @var mixed */
    protected $maxValue;

    /** @var bool */
    protected static $not = false;

    /**
     * @param string|Alias|Identifier|Literal $identifier
     * @param scalar|Literal $minValue
     * @param scalar|Literal $maxValue
     */
    public function __construct($identifier, $minValue, $maxValue)
    {
        self::assertValidIdentifier($identifier);
        $this->identifier = $identifier;

        self::assertValidLimit($minValue);
        self::assertValidLimit($maxValue);

        $this->minValue = $minValue;
        $this->maxValue = $maxValue;
    }

    /**
     * @param mixed $value
     * @return void
     * @throws InvalidArgumentException
     */
    protected static function assertValidLimit($value)
    {
        $is_valid = is_scalar($value) || $value instanceof Literal;
        if (!$is_valid) {
            throw new InvalidArgumentException(sprintf(
                "A BETWEEN predicate value must be either a scalar or an Sql Literal"
                . " expression instance, `%s` provided in class``%s!",
                is_object($value) ? get_class($value) : gettype($value),
                static::class
            ));
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
        $operator = static::$not ? Sql::NOT_BETWEEN : Sql::BETWEEN;
        $min = $this->getValueSQL($params, $this->minValue, null, 'min');
        $max = $this->getValueSQL($params, $this->maxValue, null, 'max');

        return $this->sql = "{$identifier} {$operator} {$min} AND {$max}";
    }
}
