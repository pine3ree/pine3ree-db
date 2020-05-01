<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Statement;

use InvalidArgumentException;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Statement\DML;
use P3\Db\Sql\Statement\Select;
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
class Insert extends DML
{
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
        parent::setTable($table);
        return $this;
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
     * @param array[] $values
     * @return $this
     */
    public function values(array $values)
    {
        if (empty($values)) {
            throw new InvalidArgumentException(
                'Cannot INSERT an empty set of column values!'
            );
        }

        $this->select = null;
        $this->values = [];
        foreach ($values as $value) {
            $this->value($value);
        }

        unset($this->sql, $this->sqls['values']);

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
        // Do we allow this? INSERT INTO table (value1, value2,..)
        if (empty($this->columns)) {
            throw new RuntimeException(
                "The INSERT query columns have not defined!"
            );
        }

        self::assertValidValue($value);

        if (count($this->columns) !== count($value)) {
            throw new InvalidArgumentException(
                "The INSERT value size does not match the defined columns!"
            );
        }

        if ($reset) {
            $this->values = [];
        }

        $this->select = null;
        $this->values[] = array_values($value);

        unset($this->sql, $this->sqls['values']);

        return $this;
    }

    protected static function assertValidValue($value)
    {
        if (empty($value)) {
            throw new InvalidArgumentException(
                'Cannot INSERT an empty record!'
            );
        }

        foreach ($value as $i => $v) {
            if (!is_scalar($v) && null !== $v && ! $v instanceof Literal) {
                throw new InvalidArgumentException(sprintf(
                    "A column value must be either a scalar, null or a Literal,"
                    . " `%s` provided for index {$i}",
                    is_object($v) ? get_class($v) : gettype($v)
                ));
            }
        }
        if (empty($value)) {
            throw new RuntimeException(
                'Cannot INSERT an empty record!'
            );
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

        $this->select = null;
        unset($this->sql, $this->sqls['values']);

        $this->values[] = array_values($row);

        return $this;
    }

    private static function assertValidColumns(array $columns)
    {
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

    public function getSQL(): string
    {
        if (isset($this->sql)) {
            return $this->sql;
        }

       if (empty($this->table)) {
            throw new RuntimeException(
                "The INSERT table has not been defined!"
            );
        }

        if (empty($this->columns)) {
            throw new RuntimeException(
                "The INSERT column list has not been defined!"
            );
        }

        if (empty($this->values) && empty($this->select)) {
            throw new RuntimeException(
                "The INSERT values or select statement have not been defined!"
            );
        }

        $insert  = $this->ignore ? "INSERT IGNORE" : "INSERT";
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

        if ($this->select instanceof Select) {
            return $this->sql = "{$insert} INTO {$table} {$columns} {$values}";
        }

        return $this->sql = "{$insert} INTO {$table} {$columns} VALUES {$values}";
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

        if ($this->select instanceof Select) {
            $sql = $this->select->getSQL();
            if ($this->isEmptySQL($sql)) {
                return $this->sqls['values'] = '';
            }
            if ($this->select->hasParams()) {
                $this->importParams($this->select);
            }
            return $this->sqls['values'] = "({$sql})";
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
        foreach ($value as $v) {
            $sqls[] = $v instanceof Literal
                ? $v->getSQL()
                : $this->createNamedParam($v);
        }

        return "(" . implode(", ", $sqls) . ")";
    }

    public function __get(string $name)
    {
        if ('table' === $name) {
            return $this->table;
        };
        if ('ignore' === $name) {
            return $this->ignore;
        };
        if ('into' === $name) {
            return $this->table;
        };
        if ('columns' === $name) {
            return $this->columns;
        };
        if ('values' === $name) {
            return $this->values;
        };
        if ('select' === $name) {
            return $this->select;
        };
    }
}
