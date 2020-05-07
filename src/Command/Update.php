<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Command;

use InvalidArgumentException;
use PDO;
use P3\Db\Db;
use P3\Db\Command;
use P3\Db\Sql\Statement\Update as SqlUpdate;

/**
 * Class Update
 *
 * @property-read SqlUpdate $statement
 */
class Update extends Command
{
    public function __construct(Db $db, string $table = null)
    {
        parent::__construct($db, new SqlUpdate($table));
    }

    /**
     * @see SqlUpdate::table()
     * @return $this
     */
    public function table($table): self
    {
        $this->statement->table($table);
        return $this;
    }

    /**
     * @see SqlUpdate::set()
     * @return $this
     */
    public function set($columnOrRow, $value = null): self
    {
        $this->statement->set($columnOrRow, $value);
        return $this;
    }

    public function execute()
    {
        $stmt = $this->prepare(true);
        if ($stmt === false || false === $stmt->execute()) {
            return false;
        }

        return $stmt->rowCount();
    }
}
