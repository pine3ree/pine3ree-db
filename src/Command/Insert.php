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
use P3\Db\Sql\Statement\Insert as SqlInsert;
use P3\Db\Sql\Statement\Select as SqlSelect;

/**
 * Class Insert
 *
 * @property-read SqlInsert $sqlStatement
 * @property-read string|null $table The db table to insert into if already set
 * @property-read bool $ignore Is it an INSERT IGNORE statement
 * @property-read string|null $into Alias of $table
 * @property-read string[] $columns The insert column list
 * @property-read array[] $values An array of INSERT values
 * @property-read Select|null $select The source Select statement if any
 * @property-read array[] $rows An array of GROUP BY identifiers
 */
class Insert extends Command implements WriterInterface
{
    use WriterTrait;

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
        $this->sqlStatement->ignore();
        return $this;
    }

    /**
     * @see SqlInsert::into()
     * @return $this
     */
    public function into($table): self
    {
        $this->sqlStatement->into($table);
        return $this;
    }

    /**
     * @see SqlInsert::columns()
     * @return $this
     */
    public function columns(array $columns): self
    {
        $this->sqlStatement->columns($columns);
        return $this;
    }

    /**
     * @see SqlInsert::values()
     * @return $this
     */
    public function values(array $values, bool $reset = false)
    {
        $this->sqlStatement->values($values, $reset);
        return $this;
    }

    /**
     * @see SqlInsert::multipleValues()
     * @return $this
     */
    public function multipleValues(array $multiple_values, bool $reset = false)
    {
        $this->sqlStatement->multipleValues($multiple_values, $reset);
        return $this;
    }

    /**
     * @see SqlInsert::row()
     * @return $this
     */
    public function row(array $row)
    {
        $this->sqlStatement->row($row);
        return $this;
    }

    /**
     * @see SqlInsert::rows()
     * @return $this
     */
    public function rows(array $rows)
    {
        $this->sqlStatement->rows($rows);
        return $this;
    }

    /**
     * @see SqlInsert::select()
     * @return $this
     */
    public function select(SqlSelect $select)
    {
        $this->sqlStatement->select($select);
        return $this;
    }
}
