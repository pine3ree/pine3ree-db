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
use P3\Db\Sql\DriverInterface;
use P3\Db\Sql\Params;
use P3\Db\Sql\Statement;
use P3\Db\Sql\TableAwareTrait;
use P3\Db\Exception\RuntimeException;

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

    /**
     * @param string $table The db table name to delete from
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
     * @return $this Fluent interface
     */
    public function from(string $table): self
    {
        $this->setTable($table);
        return $this;
    }

    public function getSQL(DriverInterface $driver = null, Params $params = null): string
    {
        if (isset($this->sql) && $driver === $this->driver && $params === null) {
            return $this->sql;
        }

        if (empty($this->table)) {
            throw new RuntimeException(
                "The DELETE FROM table has not been defined!"
            );
        }

        $this->driver = $driver; // set last used driver argument
        $this->params = null; // reset previously collected params, if any

        $driver = $driver ?? Driver::ansi();
        $params = $params ?? ($this->params = new Params());

        $table = $driver->quoteIdentifier($this->table);

        $where_sql = $this->getWhereSQL($driver, $params);
        if (self::isEmptySQL($where_sql)) {
            throw new RuntimeException(
                "DELETE queries without conditions are not allowed!"
            );
        }

        $this->sql = Sql::DELETE . " " . Sql::FROM . " {$table} {$where_sql}";
        return $this->sql;
    }

    /**
     * @return mixed
     */
    public function __get(string $name)
    {
        if ('table' === $name) {
            return $this->table;
        }

        if ('from' === $name) {
            return $this->table;
        }

        if ('where' === $name) {
            if (!isset($this->where)) {
                $this->where = new Where();
                $this->where->parent = $this;
            }
            return $this->where;
        }

        return parent::__get($name);
    }

    public function __clone()
    {
        parent::__clone();
        if (isset($this->where)) {
            $this->where = clone $this->where;
            $this->where->parent = $this;
        }
    }
}
