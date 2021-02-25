<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Command;

use P3\Db\Db;
use P3\Db\Command;
use P3\Db\Command\Traits\Writer as WriterTrait;
use P3\Db\Command\Writer as WriterInterface;
use P3\Db\Sql\Statement\Update as SqlUpdate;

/**
 * Class Update
 *
 * @property-read SqlUpdate $statement
 */
class Update extends Command implements WriterInterface
{
    use WriterTrait;

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
    public function set($column_or_row, $value = null): self
    {
        $this->statement->set($column_or_row, $value);
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
