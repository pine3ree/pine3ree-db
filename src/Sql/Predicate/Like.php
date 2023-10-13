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

    /**
     * @param mixed $pattern
     *
     * @throws InvalidArgumentException
     */
    protected static function assertValidPattern($pattern): void
    {
        if (!is_string($pattern) && ! $pattern instanceof Literal) {
            throw new InvalidArgumentException(sprintf(
                "A LIKE pattern must be either a string or an Sql Literal expression instance, `%s` provided!",
                is_object($pattern) ? get_class($pattern) : gettype($pattern)
            ));
        }
    }

    /**
     * @param string $escape
     *
     * @throws InvalidArgumentException
     */
    protected static function assertValidEscapeCharacter(string $escape): void
    {
        if (strlen($escape) !== 1) {
            throw new InvalidArgumentException(
                "The ESCAPE character must be either NULL or a 1-char string, `{$escape}` provided!"
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
        $operator = static::$not ? Sql::NOT_LIKE : Sql::LIKE;
        $param = $this->getValueSQL($params, $this->pattern, PDO::PARAM_STR, 'like');
        $escape = !empty($this->escape) ? " " . Sql::ESCAPE . " " . $driver->quoteValue($this->escape) : "";

        return $this->sql = "{$identifier} {$operator} {$param}{$escape}";
    }
}
