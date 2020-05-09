<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Command\Traits;

/**
 * A writer-command's execution affects persisted data
 */
trait Writer
{
    /**
     * Execute the command returning either the number of affected rows or false on error
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
