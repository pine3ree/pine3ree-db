<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\Db\Command;

use PDOStatement;

/**
 * A reader-command's statement execution returns row-sets
 */
interface Reader
{
    /**
     * Prepare, execute and return the PDO-statement or return false on failure
     *
     * @return PDOStatement|false
     */
    public function query();
}
