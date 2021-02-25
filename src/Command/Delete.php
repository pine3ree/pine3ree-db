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
use P3\Db\Sql\Statement\Delete as SqlDelete;

/**
 * Class Delete
 *
 * @property-read SqlDelete $statement
 */
class Delete extends Command implements WriterInterface
{
    use WriterTrait;

    public function __construct(Db $db, string $table = null)
    {
        parent::__construct($db, new SqlDelete($table));
    }

    /**
     * @see SqlDelete::from()
     * @return $this
     */
    public function from($table): self
    {
        $this->statement->from($table);
        return $this;
    }

    /**
     * @see SqlDelete::where()
     * @return $this
     */
    public function where($where): self
    {
        $this->statement->where($where);
        return $this;
    }
}
