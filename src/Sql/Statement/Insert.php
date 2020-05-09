<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Statement;

use InvalidArgumentException;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Statement;
use P3\Db\Sql\Statement\Traits\TableAwareTrait;
use RuntimeException;

/**
 * This class represent an INSERT SQL statement
 *
 * @property-read string|null $table The db table to inserto into if already set
 * @property-read bool $ignore Is it an INSERT IGNORE statement
 * @property-read string|null $into Alias of $table
 * @property-read string[] $columns The insert column list
 * @property-read array[] $values An array of INSERT values
 * @property-read Select|null $select The source Select statement if any
 * @property-read array[] $rows An array of GROUP BY identifiers
 */
class Insert extends Statement
{
    use TableAwareTrait;

    /** @var bool */
    private $ignore = false;

    /** @var string[] */
    private $columns = [];

    /** @var array[]|null */
    private $values;

    /** @var Select */
    private $select;

    /**
     * @param array|string $table
     * @param string $alias
     */
    public function __construct(string $table = null)
    {
        if (!empty($table)) {
            $this->into($table);
        }
    }

    public function ignore(): self
    {
        $this->ignore = true;
        return $this;
    }

    public function into($table): self
    {
        $this->setTable($table);
        return $this;
    }

    /**
     * Define the INSERT columns, this will also cleare any defined values
     *
     * @param array $columns
     * @return $this
     */
    public function columns(array $columns): self
    {
        if (empty($columns)) {
            throw new RuntimeException(
                "Missing columns definitions in INSERT SQL!"
            );
        }

        if (empty($columns)) {
            throw new RuntimeException(
                "Missing columns definitions in INSERT SQL!"
            );
        }

        self::assertValidColumns($columns);

        $this->columns = $columns;
        $this->values = [];

        unset($this->sql, $this->sqls['columns'], $this->sqls['values']);

        return $this;
    }

    /**
     * Add a single row-values list to the INSERT statement
     *
     * @param array $values
     * @param bool $reset Reset the values for a single insert
     * @return $this
     * @throws RuntimeException
     */
    public function values(array $values, bool $reset = false)
    {
        self::assertValidValues($values);

        if (!empty($this->columns)
            && count($this->columns) !== count($values)
        ) {
            throw new InvalidArgumentException(
                "The INSERT value size does not match the defined columns!"
            );
        }

        if ($reset) {
            $this->values = [];
        }

        $this->select = null;
        $this->values[] = array_values($values);

        unset($this->sql, $this->sqls['values']);

        return $this;
    }

    /**
     * Set the values of the records to be INSERTed
     *
     * @param array[] $multiple_values
     * @param bool $reset Reset the values for a single insert
     * @return $this
     */
    public function multipleValues(array $multiple_values, bool $reset = false)
    {
        if (empty($multiple_values)) {
            throw new InvalidArgumentException(
                'Cannot INSERT an empty set of column values!'
            );
        }

        $this->select = null;
        if ($reset) {
            $this->values = [];
        }
        foreach ($multiple_values as $values) {
            $this->values($values);
        }

        unset($this->sql, $this->sqls['values']);

        return $this;
    }

    protected static function assertValidValues($values)
    {
        if (empty($values)) {
            throw new InvalidArgumentException(
                'Cannot INSERT an empty record!'
            );
        }

        foreach ($values as $i => $value) {
            if (!is_scalar($value) && null !== $value && ! $value instanceof Literal) {
                throw new InvalidArgumentException(sprintf(
                    "A column value must be either a scalar, null or a Literal,"
                    . " `%s` provided for index {$i}",
                    is_object($value) ? get_class($value) : gettype($value)
                ));
            }
        }
    }

    /**
     * Set the rows to be INSERTed
     *
     * @param array[] $rows An array of new records
     * @return $this
     */
    public function rows(array $rows)
    {
        $this->select = null;
        $this->columns = [];
        $this->values = [];

        foreach ($rows as $row) {
            $this->row($row);
        }

        unset($this->sql, $this->sqls['columns'], $this->sqls['values']);

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
            $this->columns(array_keys($row));
        }

        if ($this->columns !== array_keys($row)) {
            throw new RuntimeException(sprintf(
                "The INSERT row keys %s do not match the previously defined insert columns %s!",
                json_encode(array_keys($row)),
                json_encode($this->columns)
            ));
        }

        $this->select = null;
        $this->values[] = array_values($row);

        unset($this->sql, $this->sqls['values']);

        return $this;
    }

    private static function assertValidColumns(array $columns)
    {
        if (empty($columns)) {
            throw new RuntimeException(
                "When specified, the INSERT column list must be a not empty!"
            );
        }

        foreach ($columns as $column) {
            if (!is_string($column) || is_numeric($column)) {
                throw new RuntimeException(sprintf(
                    'The INSERT columns must be valid table column names, `%s` provided!',
                    is_string($column) ? $column : gettype($column)
                ));
            }
        }
    }

    /**
     * Set the values of the records to be INSERTed
     *
     * @param Select $select The select from statement used as source
     * @return $this
     */
    public function select(Select $select)
    {
        $this->values = null;
        $this->select = $select;

        unset($this->sql, $this->sqls['values']);

        return $this;
    }

    public function getSQL(Driver $driver = null): string
    {
        if (isset($this->sql)) {
            return $this->sql;
        }

       if (empty($this->table)) {
            throw new RuntimeException(
                "The INSERT table has not been defined!"
            );
        }

        if (empty($this->values) && empty($this->select)) {
            throw new RuntimeException(
                "The INSERT values or select statement have not been defined!"
            );
        }

        $driver = $driver ?? Driver::ansi();

        $insert  = $this->ignore ? "INSERT IGNORE" : "INSERT";
        $table   = $driver->quoteIdentifier($this->table);
        $columns = $this->getColumnsSQL($driver);
        $values  = $this->getValuesSQL($driver);

        $column_list = empty($columns) ? "" : "{$columns} ";

        if (empty($values)) {
            throw new RuntimeException(
                "Missing values definitions in INSERT SQL!"
            );
        }

        if ($this->select instanceof Select) {
            return $this->sql = "{$insert} INTO {$table} {$column_list}{$values}";
        }

        return $this->sql = "{$insert} INTO {$table} {$column_list}VALUES {$values}";
    }

    private function getColumnsSQL(Driver $driver): string
    {
        if (empty($this->columns)) {
            return '';
        }

        if (isset($this->sqls['columns'])) {
            return $this->sqls['columns'];
        }

        $sqls = [];
        foreach ($this->columns as $column) {
            $sqls[] = $driver->quoteIdentifier($column);
        }

        return $this->sqls['columns'] = "(" . implode(", ", $sqls) . ")";
    }

    private function getValuesSQL(Driver $driver): string
    {
        if (isset($this->sqls['values'])) {
            return $this->sqls['values'];
        }

        // INSERT...SELECT
        if ($this->select instanceof Select) {
            $sql = $this->select->getSQL($driver);
            if (Sql::isEmptySQL($sql)) {
                return $this->sqls['values'] = '';
            }
            if ($this->select->hasParams()) {
                $this->importParams($this->select);
            }
            return $this->sqls['values'] = "({$sql})";
        }

        // INSERT...VALUES
        $sqls = [];
        foreach ($this->values as $values) {
            $sqls[] = $this->getRowValuesSQL($values);
        }

        return $this->sqls['values'] = implode(", ", $sqls);
    }

    private function getRowValuesSQL(array $values): string
    {
        $sqls = [];
        foreach ($values as $value) {
            $sqls[] = $value instanceof Literal
                ? $value->getSQL()
                : $this->createNamedParam($value);
        }

        return "(" . implode(", ", $sqls) . ")";
    }

    public function __get(string $name)
    {
        if ('table' === $name) {
            return $this->table;
        }
        if ('ignore' === $name) {
            return $this->ignore;
        }
        if ('into' === $name) {
            return $this->table;
        }
        if ('columns' === $name) {
            return $this->columns;
        }
        if ('values' === $name) {
            return $this->values;
        }
        if ('select' === $name) {
            return $this->select;
        }
    }
}
