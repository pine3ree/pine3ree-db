<?php

/**
 * @package     p3-db
 * 
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Command;

use P3\Db\Db;
use P3\Db\Command;
use P3\Db\Command\Traits\Writer;
use P3\Db\Sql\Statement\Insert as SqlInsert;
use P3\Db\Sql\Statement\Select as SqlSelect;

/**
 * Class Insert
 *
 * @property-read SqlInsert $statement
 */
class Insert extends Command
{
    use Writer;

    public function __construct(Db $db, string $table = null)
    {
        parent::__construct($db, new SqlInsert($table));
    }

    /**
     * @see SqlInsert::ignore()
     * @return $this
     */
    public function ignore(): self
    {
        $this->statement->ignore($table);
        return $this;
    }

    /**
     * @see SqlInsert::into()
     * @return $this
     */
    public function into($table): self
    {
        $this->statement->into($table);
        return $this;
    }

    /**
     * @see SqlInsert::columns()
     * @return $this
     */
    public function columns(array $columns): self
    {
        $this->statement->columns($columns);
        return $this;
    }

    /**
     * @see SqlInsert::values()
     * @return $this
     */
    public function values(array $values, bool $reset = false)
    {
        $this->statement->values($values, $reset);
        return $this;
    }

    /**
     * @see SqlInsert::multipleValues()
     * @return $this
     */
    public function multipleValues(array $value, bool $reset = false)
    {
        $this->statement->multipleValues($value, $reset);
        return $this;
    }

    /**
     * @see SqlInsert::rows()
     * @return $this
     */
    public function rows(array $rows)
    {
        $this->statement->rows($rows);
        return $this;
    }

    /**
     * @see SqlInsert::select()
     * @return $this
     */
    public function select(SqlSelect $select)
    {
        $this->statement->select($select);
        return $this;
    }
}
