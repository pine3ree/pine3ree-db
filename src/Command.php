<?php

/**
 * @package     p3-db
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db;

use PDOStatement;
use P3\Db\Sql\Statement as SqlStatement;

/**
 * A Command sends an SQL-statement to the database and returns the result
 *
 * @property-read SqlStatement $statement
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
    protected $statement;

    public function __construct(Db $db, SqlStatement $statement)
    {
        $this->db = $db;
        $this->statement = $statement;
    }

    /**
     * Prepare the SQL statement
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

    public function getParamsTypes(): array
    {
        return $this->statement->getParamsTypes();
    }

    public function getStatement(): SqlStatement
    {
        return $this->statement;
    }

    public function getSQL(): string
    {
        return $this->statement->getSQL($this->db->getDriver());
    }

    /**
     * @return PDOStatement|null|false|int Returns a PDOStatement or null for
     *      reader-commands, false or int for writer commands
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
