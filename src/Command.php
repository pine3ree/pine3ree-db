<?php

/**
 * @package p3-db@author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db;

use PDOStatement;
use P3\Db\Sql\Statement as SqlStatement;

/**
 * A db-command sends a sql-statement to the composed database instance and
 * returns the result of the statement execution
 *
 * @property-read SqlStatement $sqlStatement
 */
abstract class Command
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

    public function getParams(): array
    {
        return $this->sqlStatement->getParams();
    }

    public function getParamsTypes(bool $return_pdo_const_names = false): array
    {
        return $this->sqlStatement->getParamsTypes($return_pdo_const_names);
    }

    public function getStatement(): SqlStatement
    {
        return $this->sqlStatement;
    }

    public function getSQL(): string
    {
        return $this->sqlStatement->getSQL($this->db->getDriver());
    }

    /**
     * @return PDOStatement|int|false Returns a PDOStatement or false for
     *      reader-commands, int or false for writer-commands
     */
    abstract public function execute();

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
