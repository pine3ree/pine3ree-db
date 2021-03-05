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
 * This class represents a sql alias
 *
 * @property-read string $alias The original unquoted alias
 */
class Alias extends Element
{
    /** @var string */
    private $alias;

    protected const VALID_PATTERN = '/^(?:[a-zA-Z]|\_)[a-zA-Z0-9\_\.]*$/';

    public function __construct(string $alias)
    {
        $alias = trim($alias);

        if ('' === $alias) {
            throw new InvalidArgumentException(
                "A SQL-alias cannot be empty!"
            );
        }

        if (!preg_match(self::VALID_PATTERN, $alias)) {
            throw new InvalidArgumentException(
                "A SQL-alias can only start with ascii letter or underscore and"
                . " contain only alphanumeric, underscore and dot characters, `{$alias}` provided!"
            );
        }

        $this->alias = $alias;
    }

    /**
     * Return a properly quoted alias
     *
     * @param Driver $driver
     * @return string
     */
    public function getSQL(Driver $driver = null): string
    {
        return $this->sql = ($driver ?? Driver::ansi())->quoteAlias($this->alias);
    }

    public function __get(string $name)
    {
        if ('alias' === $name) {
            return $this->alias;
        };

        throw new RuntimeException(
            "Undefined property {$name}!"
        );
    }
}
