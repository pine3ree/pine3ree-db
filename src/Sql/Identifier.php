<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\Db\Sql;

use pine3ree\Db\Exception\InvalidArgumentException;
use pine3ree\Db\Sql\Driver;
use pine3ree\Db\Sql\DriverInterface;
use pine3ree\Db\Sql\Element;
use pine3ree\Db\Sql\Params;

use function trim;
use function preg_match;

/**
 * This class represents a sql identifier suche as a column name or a table name
 *
 * @property-read string $identifier The original unquoted identifier
 */
class Identifier extends Element
{
    private string $identifier;

    protected const VALID_PATTERN = '/^(?:[a-zA-Z]|\_)[a-zA-Z0-9\_\.]*$/';

    public function __construct(string $identifier)
    {
        $identifier = trim($identifier);

        if ('' === $identifier) {
            throw new InvalidArgumentException(
                "A SQL-identifier cannot be empty!"
            );
        }

        if (!preg_match(self::VALID_PATTERN, $identifier)) {
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
     * {@inheritDoc}
     */
    public function getSQL(DriverInterface $driver = null, Params $params = null): string
    {
        if (isset($this->sql) && $driver === $this->driver) {
            return $this->sql;
        }

        $this->driver = $driver; // Set last used driver argument

        return $this->sql = ($driver ?? Driver::ansi())->quoteIdentifier($this->identifier);
    }

    /**
     * @return mixed
     */
    public function __get(string $name)
    {
        if ('identifier' === $name) {
            return $this->identifier;
        };

        return parent::__get($name);
    }
}
