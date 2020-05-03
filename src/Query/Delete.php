<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Query;

use PDO;
use P3\Db\Db;
use P3\Db\Query;
use P3\Db\Sql\Statement\Delete as SqlDelete;

/**
 * Class Delete
 *
 * @property-read SqlDelete $statement
 */
class Delete extends Query
{
    public function __construct(Db $db, string $table = null)
    {
        parent::__construct($db, new SqlDelete($table));
    }

    public function from($table)
    {
        $this->statement->from($table);
    }

    public function where($where)
    {
        $this->statement->where($where);
    }

    public function execute()
    {
        $stmt = $this->prepare(true);
        if ($stmt === false || false === $stmt->execute()) {
            return false;
        }

        return $stmt->rowCount();
    }
}
