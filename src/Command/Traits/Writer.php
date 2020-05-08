<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Command\Traits;

/**
 * A writer command execution returns either false or the number of affected rows
 */
trait Writer
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
