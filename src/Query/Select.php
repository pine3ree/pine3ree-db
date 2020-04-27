<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Query;

use PDO;
use P3\Db\Query;
use P3\Db\Query\ConditionsAware;
use P3\Db\Query\Expr;
use P3\Db\Sql;

/**
 * Class Select
 */
class Select extends ConditionsAware
{
    protected $quantifier;
    protected $distinct = false;
    protected $columns = self::ANY;
    protected $groupBy = [];
    protected $having;
    protected $orderBy = [];
    protected $limit;
    protected $offset;

    public const ANY = '*';

    public const QUANTIFIER_DISTINCT = 'DISTINCT';
    public const QUANTIFIER_ALL = 'ALL';

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
     * @param type $columns
     * @return $this
     */
    public function columns($columns): self
    {
        if (empty($columns)) {
            return $this;
        }

        if (Sql::STAR === $columns | is_array($columns)) {
            $this->columns = $columns;
        }

        return $this;
    }

    public function from($table, string $alias = null): self
    {
        return parent::setTable($table, $alias);
    }

    /**
     *
     * @param sring|string[] $groupBy
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

    protected function getGroupBySQL(): string
    {
        if (empty($this->groupBy)) {
            return '';
        }

        return "GROUP BY " . implode(", ", $this->groupBy);
    }

    public function having($having): self
    {
        $this->having = $having;
        return $this;
    }

    protected function getHavingSQL(): string
    {
        return $this->getClauseSQL(Sql::HAVING, $this->having);
    }

    /**
     *
     * @param type $orderBy
     * @param null|string|true $sortOrReplace Set the default sort or the replace flag
     * @return \self
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

        $this->orderBy = $orderBy + (array)$this->orderBy;

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
            throw new \InvalidArgumentException(
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

        $this->sqls['order'] = $sql = "ORDER BY " . implode(', ', $sql);

        return $sql;
    }

    public function limit(int $limit): self
    {
        $this->limit = max(0, $limit);
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);
        return $this;
    }

    public function getSQL(&$params = null, &$params_types = null): string
    {
        if (isset($this->sql)) {
            return $this->sql;
        }

        $sqls = [];

        $sqls[] = $this->getBaseSQL();

        $clauses_sql = $this->getClausesSQL();
        if ($this->isNotEmptyStatement($clauses_sql)) {
            $sqls[] = $clauses_sql;
        }

        $this->sql = implode(" ", $sqls);

        return $this->sql;
    }

    protected function getBaseSQL(): string
    {
        if (empty($this->table)) {
            return '';
        }

        $sqls = ["SELECT"];
        if ($this->distinct) {
            $sqls[] = "DISTINCT";
        }

        $sqls[] = $this->getColumnsSQL();

        $table_sql = $this->quoteIdentifier($this->table);
        if (!empty($this->alias) && $alias_sql = $this->quoteAlias($this->alias)) {
            $table_sql .= " {$alias_sql}";
        }

        $sqls[] = "FROM {$table_sql}";

//        if (!empty($this->join)) {
//            foreach ($this->join as $alias => $join) {
//                $sql[] = $join;
//            }
//        }

        return implode(" ", $sqls);
    }

    private function getColumnsSQL(): string
    {
        if ($this->columns === Sql::STAR || empty($this->columns)) {
            return $this->alias ? $this->quoteAlias($this->alias) . ".*" : "*";
        }

        $columns_sqls = [];
        foreach ($this->columns as $alias => $column) {
            if ($column instanceof Expr) {
                $columns_sqls[] = $column;
            } else {
                $column_sql = $this->normalizeColumn($column);
                if (!is_numeric($alias)) {
                    $column_sql .= " AS " . $this->quoteAlias($alias);
                }
                $columns_sqls[] = $column_sql;
            }
        }

        return implode(", ", $columns_sqls);
    }

    private function getClausesSQL(): string
    {
        $sqls = [];

        $where_sql = $this->getWhereSQL();
        if ($this->isNotEmptyStatement($where_sql)) {
            $sqls[] = $where_sql;
        }

        $group_sql = $this->getGroupBySQL();
        if ($this->isNotEmptyStatement($group_sql)) {
            $sqls[] = $group_sql;
        }

        $having_sql = $this->getHavingSQL();
        if ($this->isNotEmptyStatement($having_sql)) {
            $sqls[] = $having_sql;
        }

        $order_sql = $this->getOrderBySQL();
        if ($this->isNotEmptyStatement($order_sql)) {
            $sqls[] = $order_sql;
        }

        if ($this->limit > 0) {
            $sqls[] = "LIMIT {$this->limit}";
        }
        if ($this->offset > 0) {
            $sqls[] = "OFFSET {$this->offset}";
        }

        return implode(" ", $sqls);
    }
}
