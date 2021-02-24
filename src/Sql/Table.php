<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use InvalidArgumentException;
use P3\Db\Sql\Element;

use function current;
use function is_numeric;
use function is_string;
use function key;
use function trim;

/**
 * This class represents a literal SQL expression without parameters
 */
class Table extends Element
{
    /** * @var string */
    private $name;

    /** * @var string|null */
    private $alias;

    /**
     * @param string $name The table name
     * @param string $alias The table alias if any
     * @throws InvalidArgumentException
     */
    public function __construct(string $name, string $alias = null)
    {
        $this->name = trim($name);
        if ('' === $name) {
            throw new InvalidArgumentException(
                "A db-table name cannot be empty!"
            );
        }
        $this->alias = is_numeric($alias) ? null : $alias;
    }

    public function fromArray(array $table): self
    {
        return new self(
            current($table),
            is_string($alias = key($table)) ? $alias : null
        );
    }

    public function getSQL(Driver $driver = null): string
    {
        $driver = $driver ?? Driver::ansi();

        if (empty($this->alias)) {
            return $driver->quoteIdentifier($this->name);
        }

        return $driver->quoteIdentifier($this->name) . ' ' . $driver->quoteAlias($this->alias);
    }
}
