<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Statement;

use RuntimeException;
use P3\Db\Sql\Statement\DML;

/**
 * This class represent an INSERT SQL statement
 */
class Insert extends DML
{
    private $columns = [];
    private $values = [];

    /**
     * @param array|string $table
     * @param string $alias
     */
    public function __construct($table = null, string $alias = null)
    {
        if (!empty($table)) {
            $this->into($table, $alias);
        }
    }

    public function into($table, string $alias = null): self
    {
        return parent::setTable($table, $alias);
    }

    /**
     * Define the INSERT columns, this will also cleare any defined values
     *
     * @param type $columns
     * @return $this
     */
    public function columns(array $columns): self
    {
        if (empty($columns)) {
            return $this;
        }

        self::assertValidColumns($columns);

        $this->columns = $columns;
        $this->values = [];

        unset($this->sql, $this->sqls['columns'], $this->sqls['values']);

        return $this;
    }

    /**
     * Set the values of the records to be INSERTed
     *
     * @param array $values
     * @return $this
     */
    public function values(array $values)
    {
        $this->values = [];

        unset($this->sql, $this->sqls['values']);

        foreach ($values as $value) {
            $this->value($value);
        }

        return $this;
    }

    /**
     * Add a single row-values to the INSERT statement
     *
     * @param array $value
     * @param bool $reset Reset the values for a single insert
     * @return $this
     * @throws RuntimeException
     */
    public function value(array $value, bool $reset = false)
    {
        if (empty($this->columns)) {
            throw new RuntimeException(
                "The INSERT query columns have not defined!"
            );
        }

        if (empty($value)) {
            throw new RuntimeException(
                'Cannot INSERT an empty record!'
            );
        }

        if ($reset) {
            $this->values = [];
            unset($this->sql, $this->sqls['columns']);
        }

        if (count($this->columns) !== count($value)) {
            throw new RuntimeException(
                "The INSERT value size does not match the defined columns!"
            );
        }

        $this->values[] = array_values($value);

        unset($this->sql, $this->sqls['values']);

        return $this;
    }

    /**
     * Set the rows to be INSERTed
     *
     * @param array[] $rows An array of new records
     * @return $this
     */
    public function rows(array $rows)
    {
        $this->columns = [];
        $this->values = [];

        unset($this->sql, $this->sqls['columns'], $this->sqls['values']);

        foreach ($rows as $row) {
            $this->row($row);
        }

        return $this;
    }

    /**
     * Add a row to the INSERT statement
     *
     * @param array $row The record to insert
     * @param bool $reset Reset columns and values for a single insert
     * @return $this
     * @throws RuntimeException
     */
    public function row(array $row, bool $reset = false)
    {
        if (empty($row)) {
            throw new RuntimeException(
                'Cannot INSERT an empty row!'
            );
        }

        if ($reset) {
            $this->columns = $this->values = [];
            unset($this->sql, $this->sqls['columns'], $this->sqls['values']);
        }

        if (empty($this->columns)) {
            self::assertValidColumns($columns = array_keys($row));
            $this->columns = $columns;
        }

        if ($this->columns !== array_keys($row)) {
            throw new RuntimeException(sprintf(
                "The INSERT row keys %s do not match the previously defined insert columns %s!",
                json_encode(array_keys($row)),
                json_encode($this->columns)
            ));
        }

        unset($this->sql, $this->sqls['values']);

        $this->values[] = array_values($row);

        return $this;
    }

    private static function assertValidColumns(array $columns)
    {
        foreach ($columns as $column) {
            if (!is_string($column) || is_numeric($column)) {
                throw new RuntimeException(
                    'The INSERT columns must be non-numeric strings valid table column names!'
                );
            }
        }
    }

    public function getSQL(): string
    {
        if (isset($this->sql)) {
            return $this->sql;
        }

        if (empty($this->table) || empty($this->columns || empty($this->values))) {
            return $this->sql = '';
        }

        $table   = $this->quoteIdentifier($this->table);
        $columns = $this->getColumnsSQL();
        $values  = $this->getValuesSQL();

        if (empty($columns)) {
            throw new RuntimeException(
                "Missing columns definitions in INSERT SQL!"
            );
        }

        if (empty($values)) {
            throw new RuntimeException(
                "Missing values definitions in INSERT SQL!"
            );
        }

        return $this->sql = "INSERT INTO {$table} {$columns} VALUES {$values}";
    }

    private function getColumnsSQL(): string
    {
        if (isset($this->sqls['columns'])) {
            return $this->sqls['columns'];
        }

        $sqls = [];

        foreach ($this->columns as $column) {
            $sqls[] = $this->quoteIdentifier($column);
        }

        return $this->sqls['columns'] = "(" . implode(", ", $sqls) . ")";
    }

    private function getValuesSQL(): string
    {
        if (isset($this->sqls['values'])) {
            return $this->sqls['values'];
        }

        $sqls = [];
        foreach ($this->values as $value) {
            $sqls[] = $this->getValueSQL($value);
        }

        return $this->sqls['values'] = implode(", ", $sqls);
    }

    private function getValueSQL(array $value): string
    {
        $sqls = [];
        foreach ($value as $val) {
            $sqls[] = $marker = $this->createNamedParam($val);
        }

        return "(" . implode(", ", $sqls) . ")";
    }
}
