<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Statement;

use P3\Db\Sql;
use P3\Db\Sql\Clause\WhereAwareTrait;
use P3\Db\Sql\Clause\Where;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Statement;
use P3\Db\Sql\TableAwareTrait;
use RuntimeException;

use function rtrim;

/**
 * This class represents a DELETE sql-statement expression
 *
 * @property-read string|null $table The db table to delete from if already set
 * @property-read string|null $from Alias of $table
 * @property-read Where $where The Where clause, built on-first-access if null
 */
class Delete extends Statement
{
    use WhereAwareTrait;
    use TableAwareTrait;

    /** @var Where|null */
    protected $where;

    /**
     * @param string|array $table The db table to delete from as a string or
     *      [alias => name] array
     */
    public function __construct(string $table = null)
    {
        if (isset($table)) {
            $this->from($table);
        }
    }

    /**
     * Set the db table to delete from
     *
     * @param string $table
     * @return $this
     */
    public function from(string $table): self
    {
        $this->setTable($table);
        return $this;
    }

    public function getSQL(Driver $driver = null): string
    {
        if (isset($this->sql)) {
            return $this->sql;
        }

        if (empty($this->table)) {
            throw new RuntimeException(
                "The DELETE FROM table has not been defined!"
            );
        }

        $this->resetParams();

        $driver = $driver ?? Driver::ansi();

        $table = $driver->quoteIdentifier($this->table);

        $where_sql = $this->getWhereSQL($driver);
        if (Sql::isEmptySQL($where_sql)) {
            throw new RuntimeException(
                "DELETE queries without conditions are not allowed!"
            );
        }

        $this->sql = Sql::DELETE . " " . Sql::FROM . rtrim(" {$table} {$where_sql}");
        return $this->sql;
    }

    public function __get(string $name)
    {
        if ('table' === $name) {
            return $this->table;
        }
        if ('from' === $name) {
            return $this->table;
        }
        if ('where' === $name) {
            return $this->where ?? $this->where = new Where();
        }
    }

    public function __clone()
    {
        parent::__clone();
        if (isset($this->where)) {
            $this->where = clone $this->where;
        }
    }
}
