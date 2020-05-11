<?php

/**
 * @package p3-db@author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db;

use PDOStatement;
use P3\Db\Sql\Statement as Statement;

/**
 * A db-command sends a sql-statement to the composed database instance and
 * returns the result of the statement execution
 *
 * @property-read Statement $statement
 */
abstract class Command
{
    /**
     * @var Db
     */
    protected $db;

    /**
     * @var Statement
     */
    protected $statement;

    public function __construct(Db $db, Statement $statement)
    {
        $this->db = $db;
        $this->statement = $statement;
    }

    /**
     * Prepare the sql-statement
     *
     * @param bool $bind_params
     * @return PDOStatement|false
     */
    protected function prepare(bool $bind_params = false)
    {
        return $this->db->prepare($this->statement, $bind_params);
    }

    public function getParams(): array
    {
        return $this->statement->getParams();
    }

    public function getParamsTypes(bool $return_pdo_const_names = false): array
    {
        return $this->statement->getParamsTypes($return_pdo_const_names);
    }

    public function getStatement(): Statement
    {
        return $this->statement;
    }

    public function getSQL(): string
    {
        return $this->statement->getSQL($this->db->getDriver());
    }

    /**
     * @return PDOStatement|null|false|int Returns a PDOStatement or null for
     *      reader-commands, false or int for writer-commands
     */
    abstract public function execute();

    public function __get(string $name)
    {
        if ('statement' === $name) {
            return $this->statement;
        }

        return $this->statement->{$name} ?? null;
    }
}
