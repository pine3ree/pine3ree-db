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
     * Prepare and execute the PDO-statement and return it or return null on failure
     *
     * @return PDOStatement|null
     */
    public function execute(): ?PDOStatement
    {
        $stmt = $this->prepare(true);
        if ($stmt === false || false === $stmt->execute()) {
            return null;
        }

        return $stmt;
    }
}
