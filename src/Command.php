<?php

/**
 * @package     package
 * @subpackage  package-subpackage
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
     * @param bool $bind
     * @return PDOStatement|false
     */
    protected function prepare(bool $bind = false)
    {
        return $this->db->prepare($this->statement, $bind);
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

    public function __get(string $name)
    {
        if ('statement' === $name) {
            return $this->statement;
        }

        return $this->statement->{$name} ?? null;
    }
}
