<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\Db\Sql\Predicate;

use pine3ree\Db\Exception\InvalidArgumentException;
use pine3ree\Db\Sql\DriverInterface;
use pine3ree\Db\Sql\Params;
use pine3ree\Db\Sql\Predicate;

use function trim;

/**
 * This class represents a sql literal-expression predicate
 *
 * @property-read string $literal The literal string itself
 */
class Literal extends Predicate
{
    protected string $literal;

    public function __construct(string $literal)
    {
        $literal = trim($literal);
        if ('' === $literal) {
            throw new InvalidArgumentException(
                "A SQL-literal expression cannot be empty!"
            );
        }

        $this->sql = $this->literal = $literal;
    }

    public function getSQL(DriverInterface $driver = null, Params $params = null): string
    {
        return $this->literal;
    }

    public function __clone()
    {
        // no-op
    }

    /**
     * Do not clear literals sql cache.
     * There is no compilation involved must sql must be always set
     *
     * @return void
     */
    protected function clearSQL(): void
    {
        // no-op
    }

    /**
     * @return mixed
     */
    public function __get(string $name)
    {
        if ('literal' === $name) {
            return $this->literal;
        };

        return parent::__get($name);
    }
}
