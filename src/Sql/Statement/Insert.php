<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Statement;

use P3\Db\Exception\InvalidArgumentException;
use P3\Db\Sql;
use P3\Db\Sql\Driver;
use P3\Db\Sql\DriverInterface;
use P3\Db\Sql\Params;
use P3\Db\Sql\Statement;
use P3\Db\Sql\Statement\Select;
use P3\Db\Sql\TableAwareTrait;
use P3\Db\Exception\RuntimeException;

use function array_keys;
use function array_values;
use function count;
use function gettype;
use function implode;
use function is_numeric;
use function is_string;
use function json_encode;
use function sprintf;

/**
 * This class represent an INSERT SQL statement
 *
 * @property-read string|null $table The db table to insert into if already set
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

    /** @var int */
    private $num_columns = 0;

    /** @var array[] */
    private $values_list = [];

    /** @var Select|null */
    private $select;

    /**
     * @param string $table
     */
    public function __construct(string $table = null)
    {
        if (isset($table)) {
            $this->into($table);
        }
    }

    /**
     * Add the IGNORE flag
     *
     * @return $this Fluent interface
     */
    public function ignore(): self
    {
        $this->ignore = true;
        return $this;
    }

    /**
     * Set the target INTO clause table name
     *
     * @return $this Fluent interface
     */
    public function into(string $table): self
    {
        $this->setTable($table);
        return $this;
    }

    /**
     * Define the INSERT columns, this will also clear any defined values
     *
     * @param array $columns
     * @return $this Fluent interface
     */
    public function columns(array $columns): self
    {
        self::assertValidColumns($columns);

        $this->columns = $columns;
        $this->num_columns = count($columns);
        $this->values_list = [];

        $this->clearPartialSQL('columns');

        return $this;
    }

    /**
     * Make sure the specified columns are valid column types
     *
     * @param array $columns
     * @throws RuntimeException
     * @return void
     */
    private static function assertValidColumns(array $columns): void
    {
        if (empty($columns)) {
            throw new RuntimeException(
                "When specified, the INSERT column list must be a not empty array of column names!"
            );
        }

        foreach ($columns as $column) {
            if (!is_string($column) || is_numeric($column)) {
                throw new RuntimeException(sprintf(
                    'The INSERT columns must be valid tb-column names array, `%s` provided!',
                    is_string($column) ? $column : gettype($column)
                ));
            }
        }
    }

    /**
     * Add a single-row set of values to the INSERT statement, optionally removing
     * existing values
     *
     * @param array $values
     * @param bool $add Add current set of values to existing values?
     * @return $this Fluent interface
     * @throws RuntimeException
     */
    public function values(array $values, bool $add = false): self
    {
        self::assertValidValues($values);

        if ($this->num_columns > 0 && $this->num_columns !== count($values)) {
            throw new InvalidArgumentException(
                "The INSERT value size does not match the defined columns!"
            );
        }

        $reset = !$add;

        if ($reset) {
            $this->values_list = [];
        }

        $this->select = null;
        $this->values_list[] = array_values($values);

        $this->clearSQL();

        return $this;
    }

    /**
     * Add multiple-rows sets of values to the INSERT statement, optionally removing
     * existing values
     *
     * @param array[] $multiple_values
     * @param bool $reset Remove any existing set of values?
     * @return $this Fluent interface
     */
    public function multipleValues(array $multiple_values, bool $reset = true): self
    {
        if (empty($multiple_values)) {
            throw new InvalidArgumentException(
                'Cannot INSERT an empty set of column values!'
            );
        }

        $this->select = null;
        if ($reset) {
            $this->values_list = [];
        }

        foreach ($multiple_values as $values) {
            $this->values_list($values);
        }

        $this->clearSQL();

        return $this;
    }

    /**
     * @param array $values
     * @return void
     * @throws InvalidArgumentException
     */
    protected static function assertValidValues(array $values)
    {
        if (empty($values)) {
            throw new InvalidArgumentException(
                'Cannot INSERT an empty record!'
            );
        }

        foreach ($values as $i => $value) {
            self::assertValidValue($value, 'sql-insert ');
        }
    }

    /**
     * Set the rows(columns-to-values) to be INSERTed, optionally dicarding any
     * existing set of values
     *
     * @param array[] $rows An array of new records
     * @psalm-param <string: mixed>[] An array of new records
     * @param bool $reset Remove any existing set of values?
     * @return $this Fluent interface
     */
    public function rows(array $rows, bool $reset = true): self
    {
        $this->select = null;
        if ($reset) {
            $this->columns = [];
            $this->values_list = [];
        }

        foreach ($rows as $row) {
            $this->row($row);
        }

        $this->clearPartialSQL('columns');

        return $this;
    }

    /**
     * Add a row(columns-to-values) to the INSERT statement
     *
     * @param array $row The record to insert
     * @param bool $add Add row for multiple rows insertion?
     * @return $this Fluent interface
     * @throws RuntimeException
     */
    public function row(array $row, bool $add = false): self
    {
        if (empty($row)) {
            throw new InvalidArgumentException(
                'Cannot INSERT an empty row!'
            );
        }

        $reset = !$add;

        if ($reset) {
            $this->columns = $this->values_list = [];
            $this->clearPartialSQL('columns');
        }

        if (empty($this->columns)) {
            $this->columns(array_keys($row));
        } elseif ($this->columns !== array_keys($row)) {
            throw new RuntimeException(sprintf(
                "The INSERT row keys %s do not match the previously defined insert columns %s!",
                json_encode(array_keys($row)),
                json_encode($this->columns)
            ));
        }

        $this->select = null;
        $this->values_list[] = array_values($row);

        $this->clearSQL();

        return $this;
    }

    /**
     * Set the values of the records to be INSERTed
     *
     * @param Select $select The select from statement used as source
     * @return $this Fluent interface
     */
    public function select(Select $select): self
    {
        $this->select = $select->parentIsNot($this) ? clone $select : $select;
        $this->select->parent = $this;
        $this->values_list = [];

        $this->clearSQL();

        return $this;
    }

    public function getSQL(DriverInterface $driver = null, Params $params = null): string
    {
        if (isset($this->sql) && $driver === $this->driver && $params === null) {
            return $this->sql;
        }

        if (empty($this->table)) {
            throw new RuntimeException(
                "The INSERT table has not been defined!"
            );
        }

        if (empty($this->values_list) && empty($this->select)) {
            throw new RuntimeException(
                "The INSERT values or select statement have not been defined!"
            );
        }

        $this->driver = $driver; // set last used driver argument
        $this->params = null; // reset previously collected params, if any

        $driver = $driver ?? Driver::ansi();
        $params = $params ?? ($this->params = new Params());

        $insert  = $this->ignore ? Sql::INSERT_IGNORE : Sql::INSERT;
        $table   = $driver->quoteIdentifier($this->table);
        $columns = $this->getColumnsSQL($driver);
        $values  = $this->getValuesSQL($driver, $params);

        if (empty($values)) {
            // @codeCoverageIgnoreStart
            // should be unreacheable code
            throw new RuntimeException(
                "Missing values definitions in INSERT SQL!"
            );
            // @codeCoverageIgnoreEnd
        }

        $column_list = empty($columns) ? "" : "{$columns} ";

        if ($this->select instanceof Select) {
            return $this->sql = "{$insert} " . Sql::INTO . " {$table} {$column_list}{$values}";
        }

        return $this->sql = "{$insert} " . Sql::INTO . " {$table} {$column_list}" . Sql::VALUES . " {$values}";
    }

    private function getColumnsSQL(DriverInterface $driver): string
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

    private function getValuesSQL(DriverInterface $driver, Params $params): string
    {
        // INSERT...SELECT
        if ($this->select instanceof Select) {
            return $this->select->getSQL($driver, $params);
        }

        // INSERT...VALUES
        $sqls = [];
        foreach ($this->values_list as $values) {
            $sqls[] = $this->getRowValuesSQL($values, $driver, $params);
        }

        return implode(", ", $sqls);
    }

    private function getRowValuesSQL(array $values, DriverInterface $driver, Params $params): string
    {
        $sqls = [];
        foreach ($values as $value) {
            $sqls[] = $this->getValueSQL($params, $value, null, 'val');
        }

        return "(" . implode(", ", $sqls) . ")";
    }

    /**
     * @return mixed
     */
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
            return $this->values_list;
        }
        if ('select' === $name) {
            return $this->select;
        }

        throw new RuntimeException(
            "Undefined property {$name}!"
        );
    }

    public function __clone()
    {
        parent::__clone();
        if (isset($this->select)) {
            $this->select = clone $this->select;
            $this->select->parent = $this;
        }
    }
}
