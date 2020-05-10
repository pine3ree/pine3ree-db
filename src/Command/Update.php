<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Command;

use P3\Db\Db;
use P3\Db\Command;
use P3\Db\Command\Traits\Writer;
use P3\Db\Sql\Statement\Update as SqlUpdate;

/**
 * Class Update
 *
 * @property-read SqlUpdate $statement
 */
class Update extends Command
{
    use Writer;

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

    /**
     * @see SqlUpdate::where()
     * @return $this
     */
    public function where($where): self
    {
        $this->statement->where($where);
        return $this;
    }
}
