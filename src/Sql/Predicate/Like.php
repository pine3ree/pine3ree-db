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
use PDO;

use function get_class;
use function gettype;
use function is_object;
use function is_string;
use function sprintf;
use function strlen;

/**
 * This class represents a sql LIKE condition
 */
class Like extends Predicate
{
    /** @var string|Literal */
    protected $identifier;

    /** @var string|Literal */
    protected $pattern;

    /** @var string|null */
    protected $escape;

    /** @var bool */
    protected static $not = false;

    /**
     * @var int custom index counter
     */
    protected static $index = 0;

    /**
     * @param string|Literal $identifier
     * @param string|Literal $pattern
     * @param string|null $escape An optional custom escape character
     */
    public function __construct($identifier, $pattern, string $escape = null)
    {
        self::assertValidIdentifier($identifier);
        self::assertValidPattern($pattern);
        self::assertValidEscapeCharacter($escape);

        $this->identifier = $identifier;
        $this->pattern = $pattern;
        $this->escape = $escape;
    }

    protected static function assertValidPattern($pattern)
    {
        if (!is_string($pattern) && ! $pattern instanceof Literal) {
            throw new InvalidArgumentException(sprintf(
                "A LIKE pattern must be either a string or an Sql Literal expression instance, `%s` provided!",
                is_object($pattern) ? get_class($pattern) : gettype($pattern)
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

        $identifier = $this->quoteIdentifier($this->identifier, $driver);
        $operator = static::$not ? Sql::NOT_LIKE : Sql::LIKE;
        $param = $this->createSqlForValue($this->pattern, PDO::PARAM_STR, 'like');
        $escape = !empty($escape) ? " " . Sql::ESCAPE . " " . $driver->quoteValue($escape) : "";

        return $this->sql = "{$identifier} {$operator} {$param}{$escape}";
    }
}
