<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Query;

use PDO;
use P3\Db\Db;
use P3\Db\Query;
use P3\Db\Sql\Statement\Insert as SqlInsert;
use P3\Db\Sql\Statement\Select as SqlSelect;

/**
 * Class Insert
 *
 * @property-read SqlInsert $statement
 */
class Insert extends Query
{
    public function __construct(Db $db, string $table = null)
    {
        parent::__construct($db, new SqlInsert($table));
    }

    public function ignore(): self
    {
        $this->statement->ignore($table);
        return $this;
    }

    public function into($table): self
    {
        $this->statement->into($table);
        return $this;
    }

    public function columns(array $columns): self
    {
        $this->statement->columns($columns);
        return $this;
    }

    public function values(array $values)
    {
        $this->statement->values($values);
        return $this;
    }

    public function value(array $value)
    {
        $this->statement->value($value);
        return $this;
    }

    public function rows(array $rows)
    {
        $this->statement->rows($rows);
        return $this;
    }

    public function row(array $row)
    {
        $this->statement->row($row);
        return $this;
    }

    public function select(SqlSelect $select)
    {
        $this->statement->select($select);
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
