<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db;

use PDOStatement;
use P3\Db\Sql\Statement;

/**
 * Class Query
 *
 * @property-read Statement $statement
 */
abstract class Query
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

    public function getStatement(): Statement
    {
        return $this->statement;
    }

    public function __get(string $name)
    {
        if ('statement' === $name) {
            return $this->statement;
        }

        return $this->statement->$name ?? null;
    }
}
