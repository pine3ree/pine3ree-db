<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Statement;

use InvalidArgumentException;
use P3\Db\Sql\Driver;
use P3\Db\Sql;
use P3\Db\Sql\Condition\Having;
use P3\Db\Sql\Condition\On;
use P3\Db\Sql\Condition\Where;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Predicate;
use P3\Db\Sql\PredicateSet;
use P3\Db\Sql\Statement;
use P3\Db\Sql\Statement\Traits\ConditionAwareTrait;
use P3\Db\Sql\Statement\Traits\TableAwareTrait;
use PDO;
use RuntimeException;

/**
 * This class represents a SELECT sql-statement expression
 *
 * @property-read string|null $table The db table to select from if already set
 * @property-read string|Null $alias The table alias if any
 * @property-read string|null $quantifier The SELECT quantifier if any
 * @property-read string[] $columns The columns to be returned
 * @property-read string|null $from The db table to select from or a sub-select if already set
 * @property-read Where $where The Where clause, built on-first-access if null
 * @property-read array[] $joins An array of JOIN specs if any
 * @property-read array[] $groupBy An array of GROUP BY identifiers
 * @property-read Having $having The Having clause, built on-first-access if null
 * @property-read array<string, string>[] $orderBy An array of ORDER BY identifier to sort-direction pairs
 * @property-read int|null $limit The Having clause if any
 * @property-read int|null $offset The Having clause if any
 */
class Select extends Statement
{
    use ConditionAwareTrait;
    use TableAwareTrait;

    /** @var string|null */
    protected $quantifier;

    /** @var string[] */
    protected $columns = [
        Sql::ASTERISK => Sql::ASTERISK,
    ];

    /** @var string|Select */
    protected $from;

    /** @var Where|null */
    protected $where;

    /** @var array{type: string, table: string, alias: string, cond: On|Literal}[] */
    protected $joins = [];

    /** @var array */
    protected $groupBy = [];

    /** @var Having|null */
    protected $having;

    /** @var array */
    protected $orderBy = [];

    /** @var int|null */
    protected $limit;

    /** @var int|null */
    protected $offset;

    /** @var self|null */
    protected $union;

    /**
     * @param string[]|string $columns One or many column names
     * @param string $table
     * @param string|null $alias
     */
    public function __construct($columns = null, string $table = null, string $alias = null)
    {
        if (!empty($columns)) {
            $this->columns($columns);
        }
        if (!empty($table)) {
            $this->from($table, $alias);
        }
    }

    /**
     * @param string $quantifier
     * @return $this
     */
    public function quantifier(string $quantifier): self
    {
        $quantifier = strtoupper($quantifier);
        if (isset(Sql::QUANTIFIERS[$quantifier])) {
            $this->quantifier = $quantifier;
        }

        return $this;
    }

    /**
     * @param string $quantifier
     * @return $this
     */
    public function distinct(): self
    {
        $this->quantifier = Sql::DISTINCT;
        return $this;
    }

    /**
     * Set the column(s) names / literal esxpressions to fetch or the sql-asterisk
     * '*' to fetch all the columns
     *
     * The array keys may be used to specify aliases for the columns names / literal
     * expressions
     *
     * @param string|string[] $columns
     * @return $this
     */
    public function columns($columns): self
    {
        if (empty($columns)) {
            return $this;
        }

        // was a single column or the sql-asterisk "*" provided?
        if (is_string($columns)) {
            $column = trim($columns);
            $columns = [$column => $column];
        }

        self::assertValidColumns($columns);

        // trim column names
        foreach ($columns as $key => $column) {
            if (is_string($column)) {
                $columns[$key] = trim($column);
            }
        }

        $this->columns = $columns;

        unset($this->sql, $this->sqls['columns']);

        return $this;
    }

    private static function assertValidColumns($columns)
    {
        if (!is_array($columns)) {
            throw new InvalidArgumentException(sprintf(
                "The SELECT columns argument must be either the ASTERISK string,"
                . " a column name or an array of column names / literal expressions,"
                . " '%s' provided!",
                gettype($columns)
            ));
        }

        foreach ($columns as $key => $column) {
            if (is_string($column) && '' !== trim($column)) {
                continue;
            }
            if ($column instanceof self) {
                 continue;
            }
            if ($column instanceof Literal && !self::isEmptySQL($column->getSQL())) {
                continue;
            }
            throw new InvalidArgumentException(sprintf(
                "A table column must be a non empty string, a non empty Literal "
                . "expression or a Select statement, `%s provided` for index/column-alias `{$key}`!",
                is_object($column) ? get_class($column) : gettype($column)
            ));
        }
    }

    private function getColumnsSQL(Driver $driver): string
    {
        if (isset($this->sqls['columns'])) {
            return $this->sqls['columns'];
        }

        if (empty($this->columns)) {
            $sql = $this->alias ? $driver->quoteAlias($this->alias) . ".*" : "*";
            $this->sqls['columns'] = $sql;
            return $sql;
        }

        if (isset($driver) && is_callable([$driver, 'getColumnsSQL'])) {
            return $this->sqls['columns'] = $driver->getColumnsSQL($this);
        }

        $sqls = [];
        foreach ($this->columns as $key => $column) {
            if ($column === Sql::ASTERISK) {
                $column_sql = $this->alias ? $driver->quoteAlias($this->alias) . ".*" : "*";
            } else {
                if ($column instanceof Literal) {
                    $column_sql = $column->getSQL();
                } elseif ($column instanceof Literal) {
                    $column_sql = $column->getSQL($driver);
                    $this->importParams($column);
                } else {
                    $column_sql = $driver->quoteIdentifier(
                        $this->normalizeColumn($column)
                    );
                }
                // add alias?
                if (!is_numeric($key) && $key !== '' && $key !== $column) {
                    $column_sql .= " AS " . $driver->quoteAlias($key);
                }
            }
            $sqls[] = $column_sql;
        }

        $sql = trim(implode(", ", $sqls));
        $this->sqls['columns'] = $sql;

        return $sql;
    }

    /**
     * Set the SELECT FROM table or sub-Select
     *
     * @param string!Select $from
     * @param string|null $alias
     * @return $this
     */
    public function from($from, string $alias = null): self
    {
        if (is_string($from)) {
            $this->setTable($from, $alias);
            $this->from = null;
            unset($this->sqls['from']);
            return $this;
        }

        if (! $from instanceof self) {
            throw new InvalidArgumentException(sprintf(
                "The FROM clause argument can be either a table name or a"
                . " sub-select statement, `%` provided!",
                is_object($from) ? get_class($from) : gettype($from)
            ));
        }

        $this->table = null;
        $this->from  = $from;
        $this->alias = $alias;

        unset($this->sqls['from']);

        return $this;
    }

    private function getFromSQL(Driver $driver): string
    {
        if (isset($this->sqls['from'])) {
            return $this->sqls['from'];
        }

        if ($this->from instanceof self) {
            $from = "(" . $this->from->getSQL($driver) . ")";
            $this->importParams($this->from);
        } else {
            $from = $driver->quoteIdentifier($this->table);
        }

        if (!empty($this->alias) && $alias = $driver->quoteAlias($this->alias)) {
            $from .= " {$alias}";
        }

        $sql = "FROM {$from}";
        $this->sqls['from'] = $sql;

        return $sql;
    }

    /**
     * @see self::addJoin()
     */
    public function join(string $table, string $alias, $cond = null): self
    {
        return $this->addJoin(Sql::JOIN_AUTO, $table, $alias, $cond);
    }

    /**
     * @see self::addJoin()
     */
    public function innerJoin(string $table, string $alias, $cond = null): self
    {
        return $this->addJoin(Sql::JOIN_INNER, $table, $alias, $cond);
    }

    /**
     * @see self::addJoin()
     */
    public function leftJoin(string $table, string $alias, $cond = null): self
    {
        return $this->addJoin(Sql::JOIN_LEFT, $table, $alias, $cond);
    }

    /**
     * @see self::addJoin()
     */
    public function rightJoin(string $table, string $alias, $cond = null): self
    {
        return $this->addJoin(Sql::JOIN_RIGHT, $table, $alias, $cond);
    }

    /**
     * @see self::addJoin()
     */
    public function naturalJoin(string $table, string $alias, $cond = null): self
    {
        return $this->addJoin(Sql::JOIN_NATURAL, $table, $alias, $cond);
    }

    /**
     * @see self::addJoin()
     */
    public function naturalLeftJoin(string $table, string $alias, $cond = null): self
    {
        return $this->addJoin(Sql::JOIN_NATURAL_LEFT, $table, $alias, $cond);
    }

    /**
     * @see self::addJoin()
     */
    public function naturalRightJoin(string $table, string $alias, $cond = null): self
    {
        return $this->addJoin(Sql::JOIN_NATURAL_RIGHT, $table, $alias, $cond);
    }

    /**
     * @see self::addJoin()
     */
    public function crossJoin(string $table, string $alias, $cond = null): self
    {
        return $this->addJoin(Sql::JOIN_CROSS, $table, $alias, $cond);
    }

    /**
     * @see self::addJoin()
     */
    public function straightJoin(string $table, string $alias, $cond = null): self
    {
        return $this->addJoin(Sql::JOIN_STRAIGHT, $table, $alias, $cond);
    }

    /**
     * Add a join specification to this statement
     *
     * @param string $type The join type (LEFT, RIGHT, INNER, ...)
     * @param string $table The join table name
     * @param string $alias The join table alias
     * @param On|PredicateSet|Predicate|array|string $cond
     *      The join conditional usually an ON clause, but may be changed using Literal classes
     * @return $this
     */
    private function addJoin(string $type, string $table, string $alias, $cond = null): self
    {
        Sql::assertValidJoin($type);

        if (! $cond instanceof On
            && ! $cond instanceof Literal // to express USING(column)
        ) {
            $cond = new On(Sql::AND, $cond);
        }

        $this->joins[] = [
            'type'  => $type,
            'table' => $table,
            'alias' => $alias,
            'cond'  => $cond,
        ];

        unset($this->sql, $this->sqls['join']);

        return $this;
    }

    private function getJoinSQL(Driver $driver): string
    {
        if (empty($this->joins)) {
            return '';
        }

        if (isset($this->sqls['join'])) {
            return $this->sqls['join'];
        }

        $sqls = [];
        foreach ($this->joins as $join) {
            $type  = $join['type'];
            $table = $driver->quoteIdentifier($join['table']);
            $alias = $driver->quoteAlias($join['alias']);
            $cond  = $join['cond'];

            if ($cond->hasParams()) {
                $this->importParams($cond);
            }

            $cond = $cond->getSQL($driver);

            $sqls[] = trim("{$type} JOIN {$table} {$alias} {$cond}");
        }

        $this->sqls['join'] = $sql = trim(implode(" ", $sqls));
        return $sql;
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

    private function getWhereSQL(Driver $driver): string
    {
        return $this->getConditionSQL('where', $driver);
    }

    /**
     *
     * @param string|string[]|Literal|Literal[] $groupBy
     * @param bool $replace
     * @return $this
     * @throws InvalidArgumentException
     */
    public function groupBy($groupBy, bool $replace = false): self
    {
        if ($replace) {
            $this->groupBy = [];
        }

        if (is_array($groupBy)) {
            foreach ($this->groupBy as $identifier) {
                $this->groupBy($identifier);
            }
            return $this;
        }

        if (!is_string($groupBy) && ! $identifier instanceof Literal) {
            throw new InvalidArgumentException(sprintf(
                "The `groupBy` argument must be either a string or Literal or an "
                . "array of string identifiers or Literals, `%s` provided",
                gettype($groupBy)
            ));
        }

        $this->groupBy[] = $groupBy;

        return $this;
    }

    private function getGroupBySQL(Driver $driver): string
    {
        if (empty($this->groupBy)) {
            return '';
        }

        $groupBy = $this->groupBy;
        foreach ($groupBy as $key => $identifier) {
            $groupBy[$key] = $identifier instanceof Literal
                ? $identifier->getSQL($driver)
                : $driver->quoteIdentifier($identifier);
        }

        return "GROUP BY " . implode(", ", $groupBy);
    }

    /**
     * Add WHERE conditions
     *
     * @param string|array|Predicate|Having $having
     * @return $this
     */
    public function having($having): self
    {
        $this->setCondition('having', Having::class, $having);
        return $this;
    }

    private function getHavingSQL(Driver $driver): string
    {
        return $this->getConditionSQL('having', $driver);
    }

    /**
     *
     * @param string|array $orderBy
     * @param null|string|true $sortOrReplace Set the default sort or the replace flag
     * @return $this
     */
    public function orderBy($orderBy, $sortOrReplace = null): self
    {
        if (true === $sortOrReplace) {
            $this->orderBy = [];
        }

        $sort = is_string($sortOrReplace) ? strtoupper($sortOrReplace) : null;

        $orderBy = $this->nomalizeOrderBy($orderBy, $sort);
        if (empty($orderBy)) {
            return $this;
        }

        $this->orderBy += $orderBy;

        return $this;
    }

    private function nomalizeOrderBy($orderBy, string $sort = null): array
    {
        if (is_string($orderBy)) {
            if (false === strpos($orderBy, ',')) {
                $orderBy = [trim($orderBy)];
            } else {
                $orderBy = array_map('trim', explode(',', $orderBy));
            }
        }

        if (!is_array($orderBy)) {
            throw new InvalidArgumentException(
                "The ORDER BY options must be either an array or a string!"
            );
        }

        $normalized = [];

        foreach ($orderBy as $identifier => $direction) {
            if (is_numeric($identifier)) {
                $identifier = $direction;
                $direction  = $sort;
                if (strpos($identifier, ' ')) {
                    $parts = array_map('trim', explode(' ', $identifier));
                    $identifier = $parts[0];
                    $direction  = $parts[1];
                }
            }

            $normalized[$identifier] = $direction === Sql::DESC ? $direction : Sql::ASC;
        }

        return $normalized;
    }

    private function getOrderBySQL(Driver $driver): string
    {
        if (empty($this->orderBy)) {
            return '';
        }

        if (isset($this->sqls['order'])) {
            return $this->sqls['order'];
        }

        $sql = [];
        foreach ($this->orderBy as $identifier => $direction) {
            // do not quote identifier or alias, do it programmatically
            $sql[] = $driver->quoteIdentifier($identifier) . " {$direction}";
        }

        $this->sqls['order'] = $sql = "ORDER BY " . implode(", ", $sql);

        return $sql;
    }

    public function limit(int $limit): self
    {
        $this->limit = max(0, $limit);
        unset($this->sqls['limit']);

        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);
        unset($this->sqls['limit']);

        return $this;
    }

    private function getLimitSQL(Driver $driver): string
    {
        if (isset($this->sqls['limit'])) {
            return $this->sqls['limit'];
        }

        if (!isset($this->limit) && (int)$this->offset === 0) {
            return $this->sqls['limit'] = '';
        }

        if (is_callable([$driver, 'getLimitSQL'])) {
            return $this->sqls['limit'] = $driver->getLimitSQL($this);
        }

        if (isset($this->limit)) {
            $limit = $this->createNamedParam($this->limit, PDO::PARAM_INT);
            $sql = "LIMIT {$limit}";
        }
        if (isset($this->offset) && $this->offset > 0) {
            if (!isset($sql)) {
                $sql = "LIMIT " . PHP_INT_MAX;
            }
            $offset = $this->createNamedParam($this->offset, PDO::PARAM_INT);
            $sql .= " OFFSET {$offset}";
        }

        return $this->sqls['limit'] = $sql ?? '';
    }

    public function union(self $select): self
    {
        $this->union = $select;
        return $this;
    }

    public function getSQL(Driver $driver = null): string
    {
        if (isset($this->sql)) {
            return $this->sql;
        }

        $driver = $driver ?? Driver::ansi();

        $base_sql = $this->getBaseSQL($driver);
        $clauses_sql = $this->getClausesSQL($driver);

        $sql = rtrim("{$base_sql} {$clauses_sql}");

        // quote any unquoted table alias prefix
        $tb_aliases = [];
        if ($this->alias) {
            $tb_aliases[] = $this->alias;
        }
        foreach ($this->joins as $join) {
            if ($tb_alias = $join['alias']) {
                $tb_aliases[] = $tb_alias;
            }
        }
        foreach ($tb_aliases as $tb_alias) {
            $sql = str_replace(" {$tb_alias}.", " {$driver->quoteAlias($tb_alias)}.", $sql);
        }

        if (isset($driver) && is_callable([$driver, 'decorateSelectSQL'])) {
            $sql = $driver->decorateSelectSQL($this, $sql);
        }

        return $this->sql = $sql;
    }

    private function getBaseSQL(Driver $driver): string
    {
        if (empty($this->table) && empty($this->from)) {
            throw new RuntimeException(
                "The SELECT FROM source has not been defined!"
            );
        }

        $select = "SELECT";
        if ($this->quantifier) {
            $select .= " {$this->quantifier}";
        }

        $columns = $this->getColumnsSQL($driver);
        $from = $this->getFromSQL($driver);

        return "{$select} {$columns} {$from}";
    }

    private function getClausesSQL(Driver $driver): string
    {
        $sqls = [];

        $sqls[] = $this->getJoinSQL($driver);
        $sqls[] = $this->getWhereSQL($driver);
        $sqls[] = $this->getGroupBySQL($driver);
        $sqls[] = $this->getHavingSQL($driver);
        $sqls[] = $this->getOrderBySQL($driver);
        $sqls[] = $this->getLimitSQL($driver);

        if ($this->union instanceof self) {
            $union_sql = $this->union->getSQL($driver);
            if (!$this->isEmptySQL($union_sql)) {
                $sqls[] = "UNION {$union_sql}";
            }
        }

        foreach ($sqls as $index => $sql) {
            if ($this->isEmptySQL($sql)) {
                unset($sqls[$index]);
            }
        }

        return implode(" ", $sqls);
    }

    public function __get(string $name)
    {
        if ('table' === $name) {
            return $this->table;
        }
        if ('alias' === $name) {
            return $this->alias;
        }
        if ('quantifier' === $name) {
            return $this->quantifier;
        }
        if ('columns' === $name) {
            return $this->columns;
        }
        if ('from' === $name) {
            return $this->from ?? $this->table;
        }
        if ('where' === $name) {
            return $this->where ?? $this->where = new Where();
        }
        if ('joins' === $name) {
            return $this->joins;
        }
        if ('having' === $name) {
            return $this->having ?? $this->having = new Having();
        }
        if ('groupBy' === $name) {
            return $this->groupBy;
        }
        if ('orderBy' === $name) {
            return $this->orderBy;
        }
        if ('limit' === $name) {
            return $this->limit;
        }
        if ('offset' === $name) {
            return $this->offset;
        }
    }
}

