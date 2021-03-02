<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use P3\Db\Sql\Driver;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Predicate;
use PDO;

use function get_class;
use function gettype;
use function is_object;
use function is_string;
use function sprintf;

/**
 * This class represents a sql RegExp ~ OR ~* condition
 */
class RegExp extends Predicate
{
    protected $identifier;
    protected $regexp;
    protected $case_sensitive = false;
    protected static $not = false;

    /**
     * @var int custom index counter
     */
    protected static $index = 0;

    /**
     * @param string|Literal $identifier
     * @param string|Literal $regexp
     * @param bool $case_sensitive
     */
    public function __construct($identifier, string $regexp, bool $case_sensitive = false)
    {
        self::assertValidIdentifier($identifier);
        self::assertValidRegExp($regexp);

        $this->identifier = $identifier;
        $this->regexp = $regexp;
        $this->case_sensitive = $case_sensitive;
    }

    protected static function assertValidRegExp($regexp)
    {
        if (!is_string($regexp) && ! $regexp instanceof Literal) {
            throw new InvalidArgumentException(sprintf(
                "A REGEXP pattern must be either a string or an Sql Literal expression instance, `%s` provided!",
                is_object($pattern) ? get_class($pattern) : gettype($pattern)
            ));
        }
    }

    public function getSQL(Driver $driver = null): string
    {
        if (isset($this->sql)) {
            return $this->sql;
        }

        $this->resetParams();

        $driver = $driver ?? Driver::ansi();

        $identifier = self::quoteGenericIdentifier($this->identifier, $driver);

        $operator = "~";
        if (static::$not) {
            $operator = "!{$operator}";
        }
        if ($this->case_sensitive) {
            $operator = "{$operator}*";
        }

        $param = $this->createParam($this->regexp, PDO::PARAM_STR, 'regex');

        return $this->sql = "{$identifier} {$operator} {$param}";
    }
}
