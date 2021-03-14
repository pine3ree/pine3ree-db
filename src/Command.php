<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db;

use PDOStatement;
use P3\Db\CommandInterface;
use P3\Db\Sql\Params;
use P3\Db\Sql\Statement as SqlStatement;

/**
 * A db-command sends a sql-statement to the composed database instance and
 * returns the result of its execution
 *
 * @property-read SqlStatement $sqlStatement
 */
abstract class Command implements CommandInterface
{
    /**
     * @var Db
     */
    protected $db;

    /**
     * @var SqlStatement
     */
    protected $sqlStatement;

    /**
     * @var Params
     */
    protected $params;

    /**
     * @param Db $db The database abstraction layer
     * @param SqlStatement $sqlStatement The sql-statement object
     */
    public function __construct(Db $db, SqlStatement $sqlStatement)
    {
        $this->db = $db;
        $this->sqlStatement = $sqlStatement;
    }

    public function getSqlStatement(): SqlStatement
    {
        return $this->sqlStatement;
    }

    /**
     * @see Sql\Element::getSQL()
     */
    public function getSQL(): string
    {
        return $this->sqlStatement->getSQL($this->db->getDriver(true));
    }

    /**
     * {@inheritDoc}
     * @see Sql\Element::getParams()
     */
    public function getParams(): ?Params
    {
        return $this->sqlStatement->getParams();
    }

    /**
     * Prepare the sql-statement
     *
     * @see Db::prepare()
     *
     * @param bool $bind_values
     * @return PDOStatement|false
     */
    protected function prepare(bool $bind_values = false)
    {
        return $this->db->prepare($this->sqlStatement, $bind_values);
    }

    public function __get(string $name)
    {
        if ('sqlStatement' === $name) {
            return $this->sqlStatement;
        }

        return $this->sqlStatement->{$name} ?? null;
    }

    public function __clone()
    {
        $this->sqlStatement = clone $this->sqlStatement;
    }
}
