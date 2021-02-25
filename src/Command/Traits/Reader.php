<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Command\Traits;

use PDOStatement;

/**
 * A reader-command's execution returns row-sets
 */
trait Reader
{
    /**
     * Prepare, execute and return the PDO-statement or return false on failure
     *
     * @return PDOStatement|false
     */
    public function execute()
    {
        $stmt = $this->prepare(true);
        if ($stmt === false || false === $stmt->execute()) {
            return false;
        }

        return $stmt;
    }
}
