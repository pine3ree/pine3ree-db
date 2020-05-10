<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use InvalidArgumentException;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Predicate;

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
    protected $value;
    protected $case_sensitive = false;
    protected $not = false;

    /**
     * @param string|Literal $identifier
     * @param string|Literal $values
     */
    public function __construct($identifier, string $value, bool $case_sensitive = false)
    {
        self::assertValidIdentifier($identifier);

        $this->identifier = $identifier;
        $this->value = $value;
        $this->case_sensitive = $case_sensitive;
    }

    public function getSQL(Driver $driver = null): string
    {
        if (isset($this->sql)) {
            return $this->sql;
        }

        $driver = $driver ?? Driver::ansi();

        $identifier = $this->identifier instanceof Literal
            ? $this->identifier->getSQL()
            : $driver->quoteIdentifier($this->identifier);

        $operator = "~";
        if ($this->not) {
            $operator = "!{$operator}";
        }
        if ($this->case_sensitive) {
            $operator = "{$operator}*";
        }

        $param = $this->createNamedParam($this->value);

        return $this->sql = "{$identifier} {$operator} {$param}";
    }
}
