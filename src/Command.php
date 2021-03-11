<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db;

use PDOStatement;
use P3\Db\CommandInterface;
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
     * @see Sql\Element:: getSQL()
     */
    public function getSQL(): string
    {
        return $this->sqlStatement->getSQL($this->db->getDriver(true));
    }

    /**
     * @see Sql\Element:: getParams()
     */
    public function getParams(): array
    {
        return $this->sqlStatement->getParams();
    }

    /**
     * @see Sql\Element:: getParamsTypes()
     */
    public function getParamsTypes(): array
    {
        return $this->sqlStatement->getParamsTypes();
    }

    /**
     * Prepare the sql-statement
     *
     * @param bool $bind_params
     * @return PDOStatement|false
     */
    protected function prepare(bool $bind_params = false)
    {
        return $this->db->prepare($this->sqlStatement, $bind_params);
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
