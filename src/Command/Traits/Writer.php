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
     * Execute the writer-command returning either the number of affected rows or false on error
     *
     * @see P3\Db\Command\Writer::exec()
     *
     * @return int|false
     */
    public function exec()
    {
        $stmt = $this->prepare(true);
        if ($stmt === false || false === $stmt->execute()) {
            return false;
        }

        return $stmt->rowCount();
    }

    /**
     * @see P3\Db\Command::execute()
     * @see self::exec()
     *
     * @return int|false
     */
    public function execute()
    {
        return $this->exec();
    }
}
