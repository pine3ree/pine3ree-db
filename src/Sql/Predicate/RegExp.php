<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use P3\Db\Sql\Driver;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Predicate;

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
     * @param string|Literal $identifier
     * @param string|Literal $regexp
     * @param bool $case_sensitive
     */
    public function __construct($identifier, string $regexp, bool $case_sensitive = false)
    {
        self::assertValidIdentifier($identifier);

        $this->identifier = $identifier;
        $this->regexp = $regexp;
        $this->case_sensitive = $case_sensitive;
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

        $operator = "~";
        if (static::$not) {
            $operator = "!{$operator}";
        }
        if ($this->case_sensitive) {
            $operator = "{$operator}*";
        }

        $param = $this->createNamedParam($this->regexp);

        return $this->sql = "{$identifier} {$operator} {$param}";
    }
}
