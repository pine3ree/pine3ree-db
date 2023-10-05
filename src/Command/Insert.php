<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\Db\Command;

use pine3ree\Db\Db;
use pine3ree\Db\Command;
use pine3ree\Db\Command\Traits\Writer as WriterTrait;
use pine3ree\Db\Command\Writer as WriterInterface;
use pine3ree\Db\Sql\Statement\Insert as SqlInsert;
use pine3ree\Db\Sql\Statement\Select as SqlSelect;

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
     * @return $this Fluent interface
     */
    public function ignore(): self
    {
        $this->sqlStatement->ignore();
        return $this;
    }

    /**
     * @see SqlInsert::into()
     * @return $this Fluent interface
     */
    public function into(string $table): self
    {
        $this->sqlStatement->into($table);
        return $this;
    }

    /**
     * @see SqlInsert::columns()
     * @return $this Fluent interface
     */
    public function columns(array $columns): self
    {
        $this->sqlStatement->columns($columns);
        return $this;
    }

    /**
     * @see SqlInsert::values()
     * @return $this Fluent interface
     */
    public function values(array $values, bool $reset = false): self
    {
        $this->sqlStatement->values($values, $reset);
        return $this;
    }

    /**
     * @see SqlInsert::multipleValues()
     * @return $this Fluent interface
     */
    public function multipleValues(array $multiple_values, bool $reset = true): self
    {
        $this->sqlStatement->multipleValues($multiple_values, $reset);
        return $this;
    }

    /**
     * @see SqlInsert::row()
     * @return $this Fluent interface
     */
    public function row(array $row, bool $reset = false): self
    {
        $this->sqlStatement->row($row);
        return $this;
    }

    /**
     * @see SqlInsert::rows()
     * @return $this Fluent interface
     */
    public function rows(array $rows, bool $reset = true): self
    {
        $this->sqlStatement->rows($rows);
        return $this;
    }

    /**
     * @see SqlInsert::select()
     * @return $this Fluent interface
     */
    public function select(SqlSelect $select): self
    {
        $this->sqlStatement->select($select);
        return $this;
    }
}
