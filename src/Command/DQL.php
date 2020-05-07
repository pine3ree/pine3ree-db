<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Command;

use PDOStatement;
use P3\Db\Command;

/**
 * A DQL command query execution returns rowsets
 */
abstract class DQL extends Command
{
    /**
     * Prepare anad execute the PDO-statement and return it or return null on failure
     *
     * @return PDOStatement|null
     */
    public function query(): ?PDOStatement
    {
        $stmt = $this->prepare(true);
        if ($stmt === false || false === $stmt->execute()) {
            return null;
        }

        return $stmt;
    }
}
