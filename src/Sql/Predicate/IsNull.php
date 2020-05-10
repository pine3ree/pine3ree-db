<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use P3\Db\Sql\Driver;
use P3\Db\Sql\Predicate;

/**
 * This class represents a sql IS NULL predicate
 */
class IsNull extends Predicate
{
    /** @var string|Literal */
    protected $identifier;

    /** @var bool */
    protected $not = false;

    /**
     * @param string|Literal $identifier
     */
    public function __construct($identifier)
    {
        self::assertValidIdentifier($identifier);
        $this->identifier = $identifier;
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

        return $this->sql = $identifier . $this->not ? " IS NOT NULL" : " IS NULL";
    }
}
