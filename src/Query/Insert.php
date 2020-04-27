<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Query;

use PDO;
use RuntimeException;
use P3\Db\Query;

/**
 * Class Insert
 */
class Insert extends Query
{
    private $columns = [];
    private $values = [];

    private $n = 0;

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
     *
     * @param type $columns
     * @return $this
     */
    public function columns(array $columns): self
    {
        if (empty($columns)) {
            return $this;
        }

        unset($this->sql, $this->sqls['columns']);

        $this->columns = $this->normalizeColumns($columns);

        return $this;
    }

    public function values(array $values)
    {
        foreach ($values as $value) {
            $this->value($value);
        }

        return $this;
    }

    public function value(array $value)
    {
        if (empty($this->columns)) {
            throw new RuntimeException(
                "The INSERT query columns have not defined!"
            );
        }

        if (count($this->columns) !== count($value)) {
            throw new RuntimeException(
                "The INSERT value size does not match the defined columns!"
            );
        }

        unset($this->sql, $this->sqls['values']);

        $this->values[] = array_values($value);

        return $this;
    }

    public function rows(array $rows)
    {
        foreach ($rows as $row) {
            $this->row($row);
        }

        return $this;
    }

    public function row(array $row)
    {
        if (empty($this->columns)) {
            unset($this->sql, $this->sqls['columns']);
            $this->columns = $this->normalizeColumns(array_keys($row));
        }

        if (count($this->columns) !== count($row)) {
            throw new RuntimeException(
                "The INSERT row size does not match the defined columns!"
            );
        }

        if ($this->columns !== array_keys($row)) {
            throw new RuntimeException(sprintf(
                "The INSERT row keys %s do not match the defined insert columns %s!",
                json_encode(array_keys($row)),
                json_encode($this->columns)
            ));
        }

        unset($this->sql, $this->sqls['values']);

        $this->values[] = array_values($row);

        return $this;
    }

    private function normalizeColumns(array $columns): array
    {
        $normalized = [];
        foreach ($columns as $column) {
            if (!is_string($column) || is_numeric($column)) {
                throw new RuntimeException(
                    'The INSERT columns must be non-numeric strings valid table column names!'
                );
            }
            $normalized[] = $column;
        }

        return $normalized;
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

        reset($this->values);

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
