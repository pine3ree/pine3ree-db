<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\Db\Command;

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
    public function exec();
}
