<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use InvalidArgumentException;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Element;
use RuntimeException;

use function trim;
use function preg_match;

/**
 * This class represents a sql identifier suche as a column name or a table name
 *
 * @property-read string $identifier The original unquoted identifier
 */
class Identifier extends Element
{
    /** @var string */
    private $identifier;

    public function __construct(string $identifier)
    {
        $identifier = trim($identifier);

        if ('' === $identifier) {
            throw new InvalidArgumentException(
                "A SQL-identifier cannot be empty!"
            );
        }

        if (!preg_match('/^(?:[a-zA-Z]|\_)[a-zA-Z0-9\_\.]*$/', $identifier)) {
            throw new InvalidArgumentException(
                "A SQL-identifier can only start with ascii letter or underscore and"
                . " contain only alphanumeric, underscore and dot characters, `{$identifier}` provided!"
            );
        }

        $this->identifier = $identifier;
    }

    /**
     * Return a properly quoted identifier
     *
     * @param Driver $driver
     * @return string
     */
    public function getSQL(Driver $driver = null): string
    {
        return ($driver ?? Driver::ansi())->quoteIdentifier($this->identifier);
    }

    public function __get(string $name)
    {
        if ('identifier' === $name) {
            return $this->identifier;
        };

        throw new RuntimeException(
            "Undefined property {$name}!"
        );
    }
}
