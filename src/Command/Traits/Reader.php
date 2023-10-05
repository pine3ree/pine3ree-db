<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\Db\Command\Traits;

use PDOStatement;

/**
 * A reader-command's execution returns row-sets
 */
trait Reader
{
    /**
     * Prepare, execute and return the PDO-statement or return false on failure
     *
     * @see pine3ree\Db\Command\Reader::query()
     *
     * @return PDOStatement|false
     */
    public function query()
    {
        $stmt = $this->prepare(true);
        if ($stmt === false || false === $stmt->execute()) {
            return false;
        }

        return $stmt;
    }

    /**
     * @see pine3ree\Db\Command::execute()
     * @see self::query()
     *
     * @return PDOStatement|false
     */
    public function execute()
    {
        return $this->query();
    }
}
