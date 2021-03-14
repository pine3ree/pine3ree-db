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
    /** @var string|Alias|Identifier|Literal */
    protected $identifier;

    /** @var string|Literal */
    protected $pattern;

    /** @var string|null */
    protected $escape;

    /** @var bool */
    protected static $not = false;

    /**
     * @param string|Alias|Identifier|Literal $identifier
     * @param string|Literal $pattern
     * @param string|null $escape An optional custom escape character
     */
    public function __construct($identifier, $pattern, string $escape = null)
    {
        self::assertValidIdentifier($identifier);
        self::assertValidPattern($pattern);

        $this->identifier = $identifier;
        $this->pattern = $pattern;

        if (isset($escape)) {
            self::assertValidEscapeCharacter($escape);
            $this->escape = $escape;
        }
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
        if (strlen($escape) !== 1) {
            throw new InvalidArgumentException(
                "The ESCAPE character must be either NULL or a 1-char string, `{$escape}` provided!"
            );
        }
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
        $operator = static::$not ? Sql::NOT_LIKE : Sql::LIKE;
        $param = $this->getValueSQL($params, $this->pattern, PDO::PARAM_STR, 'like');
        $escape = !empty($this->escape) ? " " . Sql::ESCAPE . " " . $driver->quoteValue($this->escape) : "";

        return $this->sql = "{$identifier} {$operator} {$param}{$escape}";
    }
}
