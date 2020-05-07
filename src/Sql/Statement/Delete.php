<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Statement\DML;

namespace P3\Db\Sql\Statement;

use RuntimeException;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Condition\Where;
use P3\Db\Sql\Predicate;
use P3\Db\Sql\Statement\DML;
use P3\Db\Sql\Statement\Traits\ConditionAwareTrait;

/**
 * This class represents a DELETE sql-statement expression
 *
 * @property-read string|null $table The db table to delete from if already set
 * @property-read string|null $from Alias of $table
 * @property-read Where $where The Where clause, built on-first-access if null
 */
class Delete extends DML
{
    use ConditionAwareTrait;

    /** @var Where|null */
    protected $where;

    /**
     * @param string|array $from The db table to delete from as a string or
     *      [alias => name] array
     */
    public function __construct(string $from = null)
    {
        if (!empty($from)) {
            $this->from($from);
        }
    }

    /**
     * Set the db table to delete from
     *
     * @param string|array $table
     * @return $this
     */
    public function from($table): self
    {
        parent::setTable($table);
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

        $driver = $driver ?? Driver::ansi();

        $table = $driver->quoteIdentifier($this->table);

        $where_sql = $this->getWhereSQL($driver);
        if ($this->isEmptySQL($where_sql)) {
            throw new RuntimeException(
                "DELETE queries without conditions are not allowed!"
            );
        }

        $this->sql = trim("DELETE FROM {$table} {$where_sql}");
        return $this->sql;
    }

    /**
     * Add WHERE conditions
     *
     * @param string|array|Predicate|Where $where
     * @return $this
     */
    public function where($where): self
    {
        $this->setCondition('where', Where::class, $where);
        return $this;
    }

    private function getWhereSQL(Driver $driver = null): string
    {
        return $this->getConditionSQL('where', $driver);
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
}
