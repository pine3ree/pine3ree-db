<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Statement;

use Closure;
use InvalidArgumentException;
use P3\Db\Sql;
use P3\Db\Sql\Clause\WhereAwareTrait;
use P3\Db\Sql\Clause\Having;
use P3\Db\Sql\Clause\Join;
use P3\Db\Sql\Clause\Where;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Expression;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Predicate;
use P3\Db\Sql\Statement;
use PDO;
use RuntimeException;

use function get_class;
use function gettype;
use function implode;
use function is_array;
use function is_callable;
use function is_numeric;
use function is_object;
use function is_string;
use function max;
use function rtrim;
use function sprintf;
use function str_replace;
use function strpos;
use function strtoupper;
use function trim;

use const PHP_INT_MAX;

/**
 * This class represents a SELECT sql-statement expression
 *
 * @property-read string|null $table The db table to select from if already set
 * @property-read string|Null $alias The table alias if any
 * @property-read string|null $quantifier The SELECT quantifier if any
 * @property-read string[] $columns The columns to be returned
 * @property-read string|self|null $from The db table to select from or a sub-select if already set
 * @property-read Where $where The Where clause, built on-first-access if null
 * @property-read Join[] $joins An array of Join clauses if any
 * @property-read array[] $groupBy An array of GROUP BY identifiers
 * @property-read Having $having The Having clause, built on-first-access if null
 * @property-read array<string, string>[] $orderBy An array of ORDER BY identifier to sort-direction pairs
 * @property-read int|null $limit The Having clause if any
 * @property-read int|null $offset The Having clause if any
 * @property-read self|null $union The sql-select statement for the UNION clause, if any
 * @property-read bool|null $union_all Is it a UNION ALL clause?
 * @property-read self|null $intersect The sql-select statement for the INTERSECT clause, if any
 */
class Select extends Statement
{
    use WhereAwareTrait;

    /** @var string|null */
    protected $quantifier;

    /** @var string[] */
    protected $columns = [];

    /** @var string|self */
    protected $from;

    /** @var string|null */
    protected $alias;

    /** @var Where|null */
    protected $where;

    /** @var Join[] */
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

    /** @var bool|null */
    protected $union_all;

    /** @var self|null */
    protected $intersect;

    /**
     * @param string[]|string|Literal[]|Literal|self|self[] $columns One or
     *      many column names, Literal expressions or sub-select statements
     * @param string|self $from A db-table name or a sub-select statement
     * @param string|null $alias
     */
    public function __construct($columns = null, $from = null, string $alias = null)
    {
        if (!empty($columns)) {
            $this->columns($columns);
        }
        if (!empty($from)) {
            $this->from($from, $alias);
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
     * @param string|string[]|Literal|Literal[]|self|self[] $columns
     * @return $this
     */
    public function columns($columns): self
    {
        if (empty($columns)) {
            return $this;
        }

        // was a single column provided?
        if (!is_array($columns)) {
            $columns = [$columns];
        }

        // trim column names
        foreach ($columns as $key => $column) {
            $this->column($column, is_numeric($key) ? null : $key);
        }

        return $this;
    }

    public function column($column, string $alias = null)
    {
        self::assertValidColumn($column, $alias);

        $this->columns[$alias ?? $column] = $column;

        $this->sql = null;
        unset($this->sqls['columns']);
    }

    private static function assertValidColumn(&$column, $key = null)
    {
        if (is_string($column) && '' !== $column = trim($column)) {
            return;
        } elseif ($column instanceof Literal
            || $column instanceof Expression
            || $column instanceof self
        ) {
             return;
        }

        throw new InvalidArgumentException(sprintf(
            "A table column must be a non empty string, a non empty Expression or Literal "
            . "expression or a Select statement, `%s`%s!",
            is_object($column) ? get_class($column) : gettype($column),
            isset($key) ? " provided for index/column-alias `{$key}`" : ""
        ));
    }

    private function getColumnsSQL(Driver $driver): string
    {
        if (isset($this->sqls['columns'])) {
            return $this->sqls['columns'];
        }

        // overridden by driver?
        if (is_callable([$driver, 'getSelectColumnsSQL'])) {
            return $this->sqls['columns'] = $driver->getSelectColumnsSQL($this);
        }

        $add_tb_prefix = !empty($this->joins) && !empty($this->table);

        if (empty($this->columns)) {
            $this->columns = [Sql::ASTERISK =>  Sql::ASTERISK];
        }

        $sqls = [];
        foreach ($this->columns as $key => $column) {
            if ($column === Sql::ASTERISK) {
                $prefix = $this->alias ? $driver->quoteAlias($this->alias) : null;
                if (empty($prefix) && $add_tb_prefix) {
                    $prefix = $driver->quoteIdentifier($this->table);
                }
                $sqls[] = $prefix ? ("{$prefix}." . Sql::ASTERISK) : Sql::ASTERISK;
                continue; // no-alias
            }

            if (is_string($column)) {
                $column_sql = $driver->quoteIdentifier(
                    $this->normalizeColumn($column, $add_tb_prefix)
                );
            } elseif ($column instanceof Literal) {
                $column_sql = $column->getSQL();
            } elseif ($column instanceof Expression || $column instanceof self) {
                $column_sql = $column->getSQL($driver);
                $this->importParams($column);
            } else {
                throw new InvalidArgumentException(sprintf(
                    "Invalid db-table column type! Allowed types are: string, Literal,"
                    . " Expression, Select, `%s` provided!",
                    is_object($column) ? get_class($column) : gettype($column)
                ));
            }

            // add alias?
            if (!is_numeric($key) && $key !== '' && $key !== $column) {
                $column_sql .= " AS " . $driver->quoteAlias($key);
            }

            $sqls[] = $column_sql;
        }

        $this->sqls['columns'] = $sql = trim(implode(", ", $sqls));
        return $sql;
    }

    /**
     * Prepend the dml-statement primary-table alias or name if not already present
     *
     * @param string $column
     * @param bool $add_tb_prefix Add table prefix?
     * @return string
     */
    public function normalizeColumn(string $column, bool $add_tb_prefix = false): string
    {
        // unquote the column first
        $column = str_replace([$this->ql, $this->qr], '', $column);
        if (false === strpos($column, '.')) {
            $prefix = $this->alias ?: (
                $add_tb_prefix ? $this->table : null
            );
            return $prefix ? "{$prefix}.{$column}" : $column;
        }

        return $column;
    }

    /**
     * Set the SELECT FROM table or sub-Select
     *
     * @param string!self $from The db-table name to select from
     * @param string|null $alias The db-table alias, if any
     * @return $this
     */
    public function from($from, string $alias = null): self
    {
        if (isset($this->from)) {
            throw new RuntimeException(sprintf(
                "Cannot change the `from` for this Select, from is already set to %s!",
                $this->from instanceof self ? "a sub-select" : "table `$this->from`"
            ));
        }

        if (is_string($from)) {
            if (empty($from)) {
                throw new InvalidArgumentException(
                    "The db-table name `from` argument cannot be empty!"
                );
            }
        } elseif (! $from instanceof self) {
            throw new InvalidArgumentException(sprintf(
                "The FROM clause argument can be either a table name or a"
                . " sub-select statement, `%` provided!",
                is_object($from) ? get_class($from) : gettype($from)
            ));
        }

        $this->from = $from;

        if ($from instanceof self && empty($alias)) {
            throw new InvalidArgumentException(
                "A FROM clause with a seb-select requires an alias!"
            );
        }

        if (!empty($alias)) {
            if (false !== strpos($alias, '.')) {
                throw new InvalidArgumentException(
                    "The FROM clause table alias cannot contain a dot!"
                );
            }
            $this->alias = $alias;
        }

        return $this;
    }

    private function getFromSQL(Driver $driver): string
    {
        if (empty($this->from)) {
            throw new RuntimeException(
                "The FROM clause table or sub-select has not been defined!"
            );
        }

        if (isset($this->sqls['from'])) {
            return $this->sqls['from'];
        }

        if ($this->from instanceof self) {
            $from = "(" . $this->from->getSQL($driver) . ")";
            $this->importParams($this->from);
        } else {
            $from = $driver->quoteIdentifier($this->from);
        }

        if (!empty($this->alias)) {
            $from = trim("{$from} " . $driver->quoteAlias($this->alias));
        }

        $this->sqls['from'] = $sql = Sql::FROM . " {$from}";
        return $sql;
    }

    /**
     * @see self::addJoin()
     */
    public function join(string $table, string $alias, $specification = null, string $type = Sql::JOIN_AUTO): self
    {
        return $this->addJoin($type, $table, $alias, $specification);
    }

    /**
     * @see self::addJoin()
     */
    public function innerJoin(string $table, string $alias, $specification = null): self
    {
        return $this->addJoin(Sql::JOIN_INNER, $table, $alias, $specification);
    }

    /**
     * @see self::addJoin()
     */
    public function leftJoin(string $table, string $alias, $specification = null): self
    {
        return $this->addJoin(Sql::JOIN_LEFT, $table, $alias, $specification);
    }

    /**
     * @see self::addJoin()
     */
    public function rightJoin(string $table, string $alias, $specification = null): self
    {
        return $this->addJoin(Sql::JOIN_RIGHT, $table, $alias, $specification);
    }

    /**
     * @see self::addJoin()
     */
    public function naturalJoin(string $table, string $alias, $specification = null): self
    {
        return $this->addJoin(Sql::JOIN_NATURAL, $table, $alias, $specification);
    }

    /**
     * @see self::addJoin()
     */
    public function naturalLeftJoin(string $table, string $alias, $specification = null): self
    {
        return $this->addJoin(Sql::JOIN_NATURAL_LEFT, $table, $alias, $specification);
    }

    /**
     * @see self::addJoin()
     */
    public function naturalRightJoin(string $table, string $alias, $specification = null): self
    {
        return $this->addJoin(Sql::JOIN_NATURAL_RIGHT, $table, $alias, $specification);
    }

    /**
     * @see self::addJoin()
     */
    public function crossJoin(string $table, string $alias, $specification = null): self
    {
        return $this->addJoin(Sql::JOIN_CROSS, $table, $alias, $specification);
    }

    /**
     * @see self::addJoin()
     */
    public function straightJoin(string $table, string $alias, $specification = null): self
    {
        return $this->addJoin(Sql::JOIN_STRAIGHT, $table, $alias, $specification);
    }

    /**
     * Add a join specification to this statement
     *
     * @param string $type The join type (LEFT, RIGHT, INNER, ...)
     * @param string $table The join table name
     * @param string $alias The join table alias
     * @param On!Literal|Predicate\Set|Predicate|array|string $specification
     *      The join conditional usually an ON clause, but may be changed using Literal classes
     * @return $this
     */
    private function addJoin(string $type, string $table, string $alias, $specification = null): self
    {
        $this->joins[] = new Join($type, $table, $alias, $specification);

        $this->sql = null;
        unset($this->sqls['join']);

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
            $join_sql = $join->getSQL($driver);
            if (Sql::isEmptySQL($join_sql)) {
                continue;
            }
            $this->importParams($join);
            $sqls[] = $join_sql;
        }

        $this->sqls['join'] = $sql = trim(implode(" ", $sqls));
        return $sql;
    }

    /**
     * Add or set the GROUP BY clause elements
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
            foreach ($groupBy as $identifier) {
                $this->groupBy($identifier);
            }
            return $this;
        }

        self::assertValidIdentifier($identifier, ' select group-by ');

        $this->groupBy[] = $groupBy;

        $this->sql = null;
        unset($this->sqls['group']);

        return $this;
    }

    private function getGroupBySQL(Driver $driver): string
    {
        if (empty($this->groupBy)) {
            return '';
        }

        if (isset($this->sqls['group'])) {
            return $this->sqls['group'];
        }

        $groupBy = $this->groupBy;
        foreach ($groupBy as $key => $identifier) {
            $groupBy[$key] = $this->quoteGenericIdentifier($identifier, $driver);
        }

        $this->sqls['group'] = $sql = Sql::GROUP_BY . " " . implode(", ", $groupBy);
        return $sql;
    }

    /**
     * Set HAVING conditions
     *
     * @param string|array|Predicate|Closure|Having $having
     * @return $this
     */
    public function having($having): self
    {
        if ($having instanceof Closure) {
            $having($this->having);
            $this->sql = null;
            return $this;
        }

        $this->setConditionalClause('having', Having::class, $having);
        return $this;
    }

    private function getHavingSQL(Driver $driver): string
    {
        return $this->getConditionalClauseSQL('having', $driver);
    }

    /**
     *
     * @param string|array $orderBy
     * @param null|string|true $sortdir_or_replace Set the default sort direction or the replace flag
     * @return $this
     */
    public function orderBy($orderBy, $sortdir_or_replace = null): self
    {
        if (true === $sortdir_or_replace) {
            $this->orderBy = [];
        }

        if (empty($orderBy)) {
            return $this;
        }

        if (is_array($orderBy)) {
            foreach ($orderBy as $identifier => $sortdir) {
                if (is_numeric($identifier)) {
                    $identifier = $sortdir;
                    $sortdir = $sortdir_or_replace;
                }
                $this->orderBy($identifier, $sortdir);
            }
            return $this;
        }

        self::assertValidIdentifier($identifier, ' select order-by ');

        if (is_string($sortdir_or_replace)) {
            $sortdir = strtoupper($sortdir_or_replace) === Sql::DESC
                ? Sql::DESC
                : Sql::ASC;
        } else {
            $sortdir = Sql::ASC;
        }

        $this->orderBy[$orderBy] = $sortdir;

        $this->sql = null;
        unset($this->sqls['order']);

        return $this;
    }

    private function getOrderBySQL(Driver $driver): string
    {
        if (empty($this->orderBy)) {
            return '';
        }

        if (isset($this->sqls['order'])) {
            return $this->sqls['order'];
        }

        $sqls = [];
        foreach ($this->orderBy as $identifier => $direction) {
            // do not quote identifier or alias when defining the order-by clause,
            // do it programmatically
            $sqls[] = $this->quoteGenericIdentifier($identifier, $driver) . " {$direction}";
        }

        $this->sqls['order'] = $sql = Sql::ORDER_BY . " " . implode(", ", $sqls);
        return $sql;
    }

    public function limit(int $limit): self
    {
        $this->limit = max(0, $limit);

        $this->sql = null;
        unset($this->sqls['limit']);

        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);

        $this->sql = null;
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

        // computed by driver?
        if (is_callable([$driver, 'getLimitSQL'])) {
            return $this->sqls['limit'] = $driver->getLimitSQL($this);
        }

        // Default implementation working for MySQL, PostgreSQL and Sqlite
        // PostgreSQL also supports OFFSET without LIMIT
        if (isset($this->limit)) {
            $limit = $this->createParam($this->limit, PDO::PARAM_INT, 'limit');
            $sql = Sql::LIMIT . " {$limit}";
        }

        if (isset($this->offset) && $this->offset > 0) {
            if (!isset($sql)) {
                $sql = Sql::LIMIT . " " . PHP_INT_MAX;
            }
            $offset = $this->createParam($this->offset, PDO::PARAM_INT, 'offset');
            $sql .= " " . Sql::OFFSET . " {$offset}";
        }

        return $this->sqls['limit'] = $sql ?? '';
    }

    public function union(self $select, bool $all = false): self
    {
        if (isset($this->intersect)) {
            throw new RuntimeException(
                "Cannot add a UNION clause when an INTERSECT clause is already set!"
            );
        }

        if (!empty($select->orderBy)) {
            $select = clone $select;
            $select->orderBy = [];
        }

        $this->union = $select;
        $this->union_all = $all;

        $this->sql = null;

        return $this;
    }

    public function intersect(self $select): self
    {
        if (isset($this->union)) {
            throw new RuntimeException(
                "Cannot add an INTERSECT clause when a UNION clause is already set!"
            );
        }

        if (!empty($select->orderBy)) {
            $select = clone $select;
            $select->orderBy = [];
        }

        $this->intersect = $select;

        $this->sql = null;

        return $this;
    }

    private function getUnionOrIntersectSQL(Driver $driver): string
    {
        if ($this->union instanceof self) {
            $union_sql = $this->union->getSQL($driver);
            if (Sql::isEmptySQL($union_sql)) {
                return '';
            }
            $this->importParams($this->union);
            return ($this->union_all ? Sql::UNION_ALL : Sql::UNION) . " {$union_sql}";
        }

        if ($this->intersect instanceof self) {
            $intersect_sql = $this->intersect->getSQL($driver);
            if (Sql::isEmptySQL($intersect_sql)) {
                return '';
            }
            $this->importParams($this->intersect);
            return Sql::INTERSECT . " {$intersect_sql}";
        }

        return '';
    }

    public function getSQL(Driver $driver = null): string
    {
        if (isset($this->sql)) {
            return $this->sql;
        }

        $this->resetParams();

        $driver = $driver ?? Driver::ansi();

        $base_sql = $this->getBaseSQL($driver);
        $clauses_sql = $this->getClausesSQL($driver);

        $sql = rtrim("{$base_sql} {$clauses_sql}");

        // quote any unquoted table alias prefix
        $sql = $this->quoteTableAliases($sql, $driver);

        if (is_callable([$driver, 'decorateSelectSQL'])) {
            $sql = $driver->decorateSelectSQL($this, $sql);
        }

        return $this->sql = $sql;
    }

    private function quoteTableAliases(string $sql, Driver $driver): string
    {
        $tb_aliases = [];
        if ($this->alias) {
            $tb_aliases[] = $this->alias;
        }
        foreach ($this->joins as $join) {
            $join_tb_alias = $join->alias;
            if (!empty($join_tb_alias)) {
                $tb_aliases[] = $join_tb_alias;
            }
        }

        if (empty($tb_aliases)) {
            return $sql;
        }

        $search = $replace = [];
        foreach ($tb_aliases as $tb_alias) {
            $search[] = " {$tb_alias}.";
            $replace[] = " {$driver->quoteAlias($tb_alias)}.";
        }

        return str_replace($search, $replace, $sql);
    }

    private function getBaseSQL(Driver $driver): string
    {
        $select = Sql::SELECT;
        if ($this->quantifier) {
            $select .= " {$this->quantifier}";
        }

        $columns = $this->getColumnsSQL($driver);
        $from = $this->getFromSQL($driver);

        return trim("{$select} {$columns} {$from}");
    }

    private function getClausesSQL(Driver $driver): string
    {
        $sqls = [];

        $sqls[] = $this->getJoinSQL($driver);
        $sqls[] = $this->getWhereSQL($driver);
        $sqls[] = $this->getGroupBySQL($driver);
        $sqls[] = $this->getHavingSQL($driver);
        $sqls[] = $this->getUnionOrIntersectSQL($driver);
        $sqls[] = $this->getOrderBySQL($driver);
        $sqls[] = $this->getLimitSQL($driver);

        foreach ($sqls as $index => $sql) {
            if (Sql::isEmptySQL($sql)) {
                unset($sqls[$index]);
            }
        }

        return implode(" ", $sqls);
    }

    public function __get(string $name)
    {
        if ('table' === $name) {
            return is_string($this->from) ? $this->from : null;
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
            return $this->from;
        }
        if ('where' === $name) {
            if (isset($this->where)) {
                $this->sql = null;
            }
            return $this->where ?? $this->where = new Where();
        }
        if ('joins' === $name) {
            if (!empty($this->joins)) {
                $this->sql = null;
                unset($this->sqls['join']);
            }
            return $this->joins;
        }
        if ('having' === $name) {
            if (isset($this->having)) {
                $this->sql = null;
            }
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
        if ('union' === $name) {
            if (isset($this->union)) {
                $this->sql = null;
            }
            return $this->union;
        }
        if ('union_all' === $name) {
            return $this->union_all;
        }
        if ('intersect' === $name) {
            if (isset($this->intersect)) {
                $this->sql = null;
            }
            return $this->intersect;
        }
    }

    public function __clone()
    {
        parent::__clone();
        if (isset($this->where)) {
            $this->where = clone $this->where;
        }
        if (isset($this->having)) {
            $this->having = clone $this->having;
        }
    }
}
