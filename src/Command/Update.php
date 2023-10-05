<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace pine3ree\Db\Command;

use Closure;
use pine3ree\Db\Db;
use pine3ree\Db\Command;
use pine3ree\Db\Command\Traits\Writer as WriterTrait;
use pine3ree\Db\Command\Writer as WriterInterface;
use pine3ree\Db\Sql\Clause\Where;
use pine3ree\Db\Sql\Predicate;
use pine3ree\Db\Sql\Statement\Update as SqlUpdate;

/**
 * Class Update
 *
 * @property-read SqlUpdate $sqlStatement
 * @property-read string|null $table The db table to select from if already set
 * @property-read string|null $quantifier The SELECT quantifier if any
 * @property-read array $set The SET column/value pairs to be updated
 * @property-read Where|null $where The Where clause, built on-first-access if null
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
     * @return $this Fluent interface
     */
    public function table(string $table): self
    {
        $this->sqlStatement->table($table);
        return $this;
    }

    /**
     * @see SqlUpdate::set()
     *
     * @param string|array $column_or_row
     * @param mixed $value
     * @return $this Fluent interface
     */
    public function set($column_or_row, $value = null): self
    {
        $this->sqlStatement->set($column_or_row, $value);
        return $this;
    }

    /**
     * @see SqlUpdate::where()
     * @param string|array|Predicate|Closure|Where $where
     * @return $this Fluent interface
     */
    public function where($where): self
    {
        $this->sqlStatement->where($where);
        return $this;
    }
}
