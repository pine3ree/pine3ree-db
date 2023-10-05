<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace pine3ree\Db\Sql;

use pine3ree\Db\Exception\InvalidArgumentException;
use pine3ree\Db\Sql\Driver;
use pine3ree\Db\Sql\DriverInterface;
use pine3ree\Db\Sql\Element;
use pine3ree\Db\Sql\Params;

use function trim;
use function preg_match;

/**
 * This class abstracts a SQL fragment used as an alias
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
     */
    public function getSQL(DriverInterface $driver = null, Params $params = null): string
    {
        if (isset($this->sql) && $driver === $this->driver) {
            return $this->sql;
        }

        $this->driver = $driver;

        return $this->sql = ($driver ?? Driver::ansi())->quoteAlias($this->alias);
    }

    /**
     * @return mixed
     */
    public function __get(string $name)
    {
        if ('alias' === $name) {
            return $this->alias;
        };

        return parent::__get($name);
    }
}
