<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Command;

use Closure;
use P3\Db\Db;
use P3\Db\Command;
use P3\Db\Command\Traits\Writer as WriterTrait;
use P3\Db\Command\Writer as WriterInterface;
use P3\Db\Sql\Clause\Where;
use P3\Db\Sql\Predicate;
use P3\Db\Sql\Statement\Delete as SqlDelete;

/**
 * Class Delete
 *
 * @property-read SqlDelete $sqlStatement
 * @property-read string|null $table The db table to delete from if already set
 * @property-read string|null $from Alias of $table
 * @property-read Where $where The Where clause, built on-first-access if null
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
     *
     * @param string $table
     * @return $this Fluent interface
     */
    public function from($table): self
    {
        $this->sqlStatement->from($table);
        return $this;
    }

    /**
     * @see SqlDelete::where()
     *
     * @param string|array|Predicate|Closure|Where $where
     * @return $this Fluent interface
     */
    public function where($where): self
    {
        $this->sqlStatement->where($where);
        return $this;
    }
}
