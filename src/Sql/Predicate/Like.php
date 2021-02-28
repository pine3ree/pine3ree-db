<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use InvalidArgumentException;
use P3\Db\Sql;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Predicate;

use function get_class;
use function gettype;
use function is_object;
use function is_string;
use function sprintf;

/**
 * This class represents a sql LIKE condition
 */
class Like extends Predicate
{
    /** @var string|Literal */
    protected $identifier;

    /** @var string|Literal */
    protected $value;

    /** @var string|null */
    protected $escape;

    /** @var bool */
    protected static $not = false;

    /**
     * @param string|Literal $identifier
     * @param string|Literal $value
     * @param string|null $escape An optional custom escape character
     */
    public function __construct($identifier, $value, string $escape = null)
    {
        self::assertValidIdentifier($identifier);
        self::assertValidValue($value);
        self::assertValidEscapeCharacter($escape);

        $this->identifier = $identifier;
        $this->value = $value;
        $this->escape = $escape;
    }

    protected static function assertValidValue($value)
    {
        if (!is_string($value) && ! $value instanceof Literal) {
            throw new InvalidArgumentException(sprintf(
                "A LIKE value must be either a string or an Sql Literal expression instance, `%s` provided!",
                is_object($value) ? get_class($value) : gettype($value)
            ));
        }
    }

    protected static function assertValidEscapeCharacter(string $escape = null)
    {
        if (null === $escape) {
            return;
        }
        if (strlen($escape) !== 1) {
            throw new InvalidArgumentException(
                "The ESCAPE character must be either NULL or a 1-char string, `{$escape}` provided!"
            );
        }
    }

    public function getSQL(Driver $driver = null): string
    {
        if (isset($this->sql)) {
            return $this->sql;
        }

        $this->resetParams();

        $driver = $driver ?? Driver::ansi();

        $identifier = $this->identifier instanceof Literal
            ? $this->identifier->getSQL()
            : $driver->quoteIdentifier($this->identifier);

        $operator = static::$not ? Sql::NOT_LIKE : Sql::LIKE;

        $param = $this->value instanceof Literal
            ? $this->value->getSQL()
            : $this->createParam($this->value);

        $escape = !empty($escape) ? " " . Sql::ESCAPE . " " . $driver->quoteValue($escape) : "";

        return $this->sql = "{$identifier} {$operator} {$param}{$escape}";
    }
}
