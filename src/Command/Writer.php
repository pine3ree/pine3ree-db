<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Command;

/**
 * A writer-command's statement execution affects persisted data
 */
interface Writer
{
    /**
     * Execute the writer-command returning either the number of affected rows or null on error
     *
     * @return int|false
     */
    public function execute();
}
