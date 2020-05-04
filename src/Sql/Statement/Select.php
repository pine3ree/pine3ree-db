<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Statement;

use InvalidArgumentException;
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
            $columns = [$columns => $columns];
        }

        self::assertValidColumns($columns);

        $this->columns = $columns;

        unset($this->sql, $this->sqls['columns']);

        return $this;
    }

    private static function assertValidColumns($columns)
    {
        if (!is_array($columns)) {
            throw new InvalidArgumentException(sprintf(
                "The SELECT columns argument must be either the ASTERISK string"
                . " or an array of column names, '%s' provided!",
                gettype($columns)
            ));
        }

        foreach ($columns as $key => $column) {
            if (!is_string($column) || '' === $column) {
                throw new InvalidArgumentException(sprintf(
                    "A table column must be a non emtoy string, `%s provided` for index `{$key}`!",
                    gettype($column)
                ));
            }
        }

        $columns = array_map('trim', $columns);

    }

    private function getColumnsSQL(): string
    {
        if (isset($this->sqls['columns'])) {
            return $this->sqls['columns'];
        }

        $sqls = [];
        foreach ($this->columns as $key => $column) {
            if ($column === Sql::ASTERISK) {
                $column_sql = $this->alias ? $this->quoteAlias($this->alias) . ".*" : "*";
            } else {
                $column_sql = $column instanceof Literal
                    ? $column->getSQL()
                    : $this->quoteIdentifier(
                        $this->normalizeColumn($column)
                    );
                if (!is_numeric($key) && $key !== '' && $key !== $column) {
                    $column_sql .= " AS " . $this->quoteAlias($key);
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
     * @param On|PredicateSet|Predicate|Literal|Predicate\Literal|array|string $cond
     *      The join conditional usually an ON clause, but may be changed using Literal classes
     * @return $this
     */
    private function addJoin(string $type, string $table, string $alias, $cond = null): self
    {
        if (! $cond instanceof On
            && ! $cond instanceof Literal
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

    private function getJoinSQL(): string
    {
        if (empty($this->joins)) {
            return '';
        }

        if (isset($this->sqls['join'])) {
            return $this->sqls['join'];
        }

        $sqls = [];
        foreach ($this->joins as $join) {
            $type  = isset(Sql::JOIN_TYPES[$join['type']]) ? $join['type'] : '';
            $table = $this->quoteIdentifier($join['table']);
            $alias = $this->quoteAlias($join['alias']);
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

    private function getWhereSQL(bool $stripParentheses = false): string
    {
        return $this->getConditionSQL('where', $stripParentheses);
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

    private function getGroupBySQL(): string
    {
        if (empty($this->groupBy)) {
            return '';
        }

        return "GROUP BY " . implode(", ", $this->groupBy);
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

    private function getHavingSQL(bool $stripParentheses = false): string
    {
        return $this->getConditionSQL('having', $stripParentheses);
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
                $orderBy = [$orderBy];
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
            }

            $parts = array_map('trim', explode(' ', $identifier));
            $identifier = $parts[0];
            $direction  = $parts[1] ?? $direction ?? Sql::ASC;
            $direction  = $direction === Sql::DESC ? $direction : Sql::ASC;

            $normalized[$identifier] = $direction;
        }

        return $normalized;
    }

    private function getOrderBySQL(): string
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
            $sql[] = "{$identifier} {$direction}";
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

    private function getLimitSQL(): string
    {
        if (isset($this->sqls['limit'])) {
            return $this->sqls['limit'];
        }

        $sql = '';
        if ($this->limit > 0) {
            $limit = $this->createNamedParam($this->limit, PDO::PARAM_INT);
            $sql .= "LIMIT {$limit}";
        }
        if ($this->offset > 0) {
            $offset = $this->createNamedParam($this->offset, PDO::PARAM_INT);
            $sql .= "OFFSET {$offset}";
        }

        return $this->sqls['limit'] = $sql;
    }

    public function getSQL(bool $stripConditionsParentheses = false): string
    {
        if (isset($this->sql)) {
            return $this->sql;
        }

        $base_sql = $this->getBaseSQL();
        $clauses_sql = $this->getClausesSQL($stripConditionsParentheses);

        $this->sql = rtrim("{$base_sql} {$clauses_sql}");

        return $this->sql;
    }

    private function getBaseSQL(): string
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

        $columns = $this->getColumnsSQL();

        $table = $this->quoteIdentifier($this->table);
        if (!empty($this->alias) && $alias = $this->quoteAlias($this->alias)) {
            $table .= " {$alias}";
        }

        return "{$select} {$columns} FROM {$table}";
    }

    private function getClausesSQL(bool $stripConditionsParentheses = false): string
    {
        $sqls = [];

        $sqls[] = $this->getJoinSQL($stripConditionsParentheses);
        $sqls[] = $this->getWhereSQL($stripConditionsParentheses);
        $sqls[] = $this->getGroupBySQL();
        $sqls[] = $this->getHavingSQL($stripConditionsParentheses);
        $sqls[] = $this->getOrderBySQL();
        $sqls[] = $this->getLimitSQL();

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

