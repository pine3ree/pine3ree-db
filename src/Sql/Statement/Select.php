<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
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
use P3\Db\Sql\Statement\DML;
use P3\Db\Sql\Statement\Traits\ConditionAwareTrait;
use PDO;
use RuntimeException;

/**
 * This class represents a SELECT sql-statement expression
 *
 * @property-read string|null $table The db table to select from if already set
 * @property-read string|Null $alias The table alias if any
 * @property-read string|null $quantifier The SELECT quantifier if any
 * @property-read string[] $columns The columns to be returned
 * @property-read string|null $from Alias of $table
 * @property-read Where $where The Where clause, built on-first-access if null
 * @property-read array[] $joins An array of JOIN specs if any
 * @property-read array[] $groupBy An array of GROUP BY identifiers
 * @property-read Having $having The Having clause, built on-first-access if null
 * @property-read array[] $orderBy An array of ORDER BY identifier to sort-direction pairs
 * @property-read int|null $limit The Having clause if any
 * @property-read int|null $offset The Having clause if any
 */
class Select extends DML
{
    use ConditionAwareTrait;

    /** @var string|null */
    protected $quantifier;

    /** @var string[] */
    protected $columns = [
        Sql::ASTERISK => Sql::ASTERISK,
    ];

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

    /** @var string|null */
    protected $indexBy;

    public function __construct($columns = null, $table = null)
    {
        if (!empty($columns)) {
            $this->columns($columns);
        }
        if (!empty($table)) {
            $this->from($table);
        }
    }

    /**
     * @param string $quantifier
     * @return $this
     */
    public function quantifier(string $quantifier): self
    {
        $quantifier = strtoupper($quantifier);
        if (in_array($quantifier, [Sql::DISTINCT, Sql::ALL], true)) {
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
                $column = $columns[$key] = trim($column);
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
            if (is_string($column)) {
                if ('' === trim($column)) {
                    throw new InvalidArgumentException(
                        "A table column string must be non empty string for index/column-alias `{$key}`!"
                    );
                }
                continue;
            }
            if (! $column instanceof Literal || '' === $column->getSQL()) {
                throw new InvalidArgumentException(sprintf(
                    "A table column must be a non empty string or Literal "
                    . "expression, `%s provided` for index/column-alias `{$key}`!",
                    is_object($column) ? get_class($column) : gettype($column)
                ));
            }
        }
    }

    private function getColumnsSQL(Driver $driver = null): string
    {
        if (isset($this->sqls['columns'])) {
            return $this->sqls['columns'];
        }

        $quoter = $driver ?? $this;

        if (empty($this->columns)) {
            $sql = $this->alias ? $quoter->quoteAlias($this->alias) . ".*" : "*";
            $this->sqls['columns'] = $sql;
            return $sql;
        }

        if (isset($driver) && is_callable([$driver, 'getColumnsSQL'])) {
            return $this->sqls['columns'] = $driver->getColumnsSQL($this);
        }

        $sqls = [];
        foreach ($this->columns as $key => $column) {
            if ($column === Sql::ASTERISK) {
                $column_sql = $this->alias ? $quoter->quoteAlias($this->alias) . ".*" : "*";
            } else {
                $column_sql = $column instanceof Literal
                    ? $column->getSQL()
                    : $quoter->quoteIdentifier(
                        $this->normalizeColumn($column)
                    );
                if (!is_numeric($key) && $key !== '' && $key !== $column) {
                    $column_sql .= " AS " . $quoter->quoteAlias($key);
                }
            }
            $sqls[] = $column_sql;
        }

        $sql = trim(implode(", ", $sqls));
        $this->sqls['columns'] = $sql;

        return $sql;
    }

    /**
     * Set the SELECT FROM table
     *
     * @param string|array $table
     * @param string|null $alias
     * @return $this
     */
    public function from($table, string $alias = null): self
    {
         parent::setTable($table, $alias);
         return $this;
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
        if (! $cond instanceof On) {
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

    private function getJoinSQL(Driver $driver = null): string
    {
        if (empty($this->joins)) {
            return '';
        }

        if (isset($this->sqls['join'])) {
            return $this->sqls['join'];
        }

        $quoter = $driver ?? $this;

        $sqls = [];
        foreach ($this->joins as $join) {
            $type  = isset(Sql::JOIN_TYPES[$join['type']]) ? $join['type'] : '';
            $table = $quoter->quoteIdentifier($join['table']);
            $alias = $quoter->quoteAlias($join['alias']);
            $cond  = $join['cond'];

            if ($cond->hasParams()) {
                $this->importParams($cond);
            }

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

    private function getWhereSQL(Driver $driver = null): string
    {
        return $this->getConditionSQL('where', $driver);
    }

    /**
     *
     * @param string|string[] $groupBy
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

        if (!is_string($groupBy)) {
            throw new InvalidArgumentException(sprintf(
                "The `groupBy` argument must be either a string or an array of"
                . " string identifiers, `%s` provided",
                gettype($groupBy)
            ));
        }

        $this->groupBy[] = $groupBy;

        return $this;
    }

    private function getGroupBySQL(Driver $driver = null): string
    {
        if (empty($this->groupBy)) {
            return '';
        }

        $quoter = $driver ?? $this;

        $groupBy = $this->groupBy;
        foreach ($groupBy as $key => $identifier) {
            $groupBy[$key] = $quoter->quoteIdentifier($identifier);
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

    private function getHavingSQL(Driver $driver = null): string
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

    private function getOrderBySQL(Driver $driver = null): string
    {
        if (empty($this->orderBy)) {
            return '';
        }

        if (isset($this->sqls['order'])) {
            return $this->sqls['order'];
        }

        $quoter = $driver ?? $this;

        $sql = [];
        foreach ($this->orderBy as $identifier => $direction) {
            // do not quote identifier or alias, do it programmatically
            $sql[] = $quoter->quoteIdentifier($identifier) . " {$direction}";
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

    private function getLimitSQL(Driver $driver = null): string
    {
        if (isset($this->sqls['limit'])) {
            return $this->sqls['limit'];
        }

        if (!isset($this->limit) && (int)$this->offset === 0) {
            return $this->sqls['limit'] = '';
        }

        if (isset($driver) && is_callable([$driver, 'getLimitSQL'])) {
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

    public function indexBy(string $indexBy): self
    {
        $this->indexBy = $indexBy;
        return $this;
    }

    public function indexedBy(): ?string
    {
        return $this->indexBy;
    }

    public function getSQL(Driver $driver = null): string
    {
        if (isset($this->sql)) {
            return $this->sql;
        }

        $base_sql = $this->getBaseSQL($driver);
        $clauses_sql = $this->getClausesSQL($driver);

        $sql = rtrim("{$base_sql} {$clauses_sql}");

        if (isset($driver) && is_callable([$driver, 'decorateSelectSQL'])) {
            $sql = $driver->decorateSelectSQL($this, $sql);
        }

        return $this->sql = $sql;
    }

    private function getBaseSQL(Driver $driver = null): string
    {
        if (empty($this->table)) {
            throw new RuntimeException(
                "The SELECT FROM table has not been defined!"
            );
        }

        $select = "SELECT";
        if ($this->quantifier) {
            $select .= " {$this->quantifier}";
        }

        $columns = $this->getColumnsSQL($driver);

        $quoter = $driver ?? $this;

        $table = $quoter->quoteIdentifier($this->table);
        if (!empty($this->alias) && $alias = $quoter->quoteAlias($this->alias)) {
            $table .= " {$alias}";
        }

        return "{$select} {$columns} FROM {$table}";
    }

    private function getClausesSQL(Driver $driver = null): string
    {
        $sqls = [];

        $sqls[] = $this->getJoinSQL($driver);
        $sqls[] = $this->getWhereSQL($driver);
        $sqls[] = $this->getGroupBySQL($driver);
        $sqls[] = $this->getHavingSQL($driver);
        $sqls[] = $this->getOrderBySQL($driver);
        $sqls[] = $this->getLimitSQL($driver);

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

