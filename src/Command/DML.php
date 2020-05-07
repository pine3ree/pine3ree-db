<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Command;

use P3\Db\Command;

/**
 * A DML command execution returns either false or the number of affected rows
 */
abstract class DML extends Command
{
    /**
     * Execute the command returning the number of affected row or false on error
     *
     * @return int|false
     */
    public function execute()
    {
        $stmt = $this->prepare(true);
        if ($stmt === false || false === $stmt->execute()) {
            return false;
        }

        return $stmt->rowCount();
    }
}
