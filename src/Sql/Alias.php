<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use InvalidArgumentException;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Element;

use function trim;
use function preg_match;

/**
 * This class represents a sql alias
 */
class Alias extends Element
{
    public function __construct(string $alias)
    {
        $sql = trim($alias);

        if ('' === $sql) {
            throw new InvalidArgumentException(
                "A SQL-alias cannot be empty!"
            );
        }

        if (!preg_match('/^(?:[a-zA-Z\]|\_)[a-zA-Z0-9\_\.]*$/', $sql)) {
            throw new InvalidArgumentException(
                "A SQL-alias can only start with ascii letter or underscore and"
                . " contain only alphanumeric, underscore and dot characters, `{$sql}` provided!"
            );
        }

        $this->sql = $sql;
    }

    public function getSQL(Driver $driver = null): string
    {
        return $this->sql;
    }

    public function clearSQL()
    {
        // no-op
    }

    public function __clone()
    {
        // no-op
    }
}
