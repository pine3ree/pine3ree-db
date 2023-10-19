<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\Db\Sql\Statement;

use Closure;
use pine3ree\Db\Exception\InvalidArgumentException;
use pine3ree\Db\Sql;
use pine3ree\Db\Sql\Alias;
use pine3ree\Db\Sql\Clause\Combine;
use pine3ree\Db\Sql\Clause\Except;
use pine3ree\Db\Sql\Clause\Intersect;
use pine3ree\Db\Sql\Clause\Union;
use pine3ree\Db\Sql\Clause\WhereAwareTrait;
use pine3ree\Db\Sql\Clause\Having;
use pine3ree\Db\Sql\Clause\Join;
use pine3ree\Db\Sql\Clause\On;
use pine3ree\Db\Sql\Clause\Where;
use pine3ree\Db\Sql\Driver;
use pine3ree\Db\Sql\Driver\Feature\LimitSqlProvider;
use pine3ree\Db\Sql\Driver\Feature\SelectColumnsSqlProvider;
use pine3ree\Db\Sql\Driver\Feature\SelectDecorator;
use pine3ree\Db\Sql\Driver\Feature\SelectSqlDecorator;
use pine3ree\Db\Sql\DriverInterface;
use pine3ree\Db\Sql\Element;
use pine3ree\Db\Sql\ElementInterface;
use pine3ree\Db\Sql\Expression;
use pine3ree\Db\Sql\Identifier;
use pine3ree\Db\Sql\Literal;
use pine3ree\Db\Sql\Params;
use pine3ree\Db\Sql\Predicate;
use pine3ree\Db\Sql\Statement;
use pine3ree\Db\Sql\TableAwareTrait;
use PDO;
use pine3ree\Db\Exception\RuntimeException;

use function get_class;
use function gettype;
use function implode;
use function is_array;
use function is_numeric;
use function is_object;
use function is_string;
use function preg_quote;
use function preg_replace;
use function sprintf;
use function str_repeat;
use function strpos;
use function strtoupper;
use function trim;

use const PHP_INT_MAX;

/**
 * This class represents a SELECT sql query specification
 *
 * @link https://ronsavage.github.io/SQL/sql-2003-2.bnf.html#query%20specification
 *
 * @property-read string|null $table The db table to select from ,if already set
 * @property-read string|null $alias The table alias, if any
 * @property-read string|null $quantifier The SELECT quantifier, if any
 * @property-read string[]|Identifier[]|Literal[]|Expression[]|self[] $columns The columns to be returned
 * @psalm-property-read array<string, string|Identifier|Literal|Expression|self> $columns
 * @property-read string|null $into The db table to insert the selected rows into,, if any
 * @property-read string|self|null $from The db table to select from or a sub-select, if already set
 * @property-read Where $where The Where clause, built on-first-access, if null
 * @property-read Join[] $joins An array of Join clauses, if any
 * @property-read array $groupBy An array of GROUP BY identifiers
 * @property-read Having $having The Having clause, built on-first-access, if null
 * @property-read array $orderBy An array of ORDER BY identifier to sort-direction pairs
 * @property-read int|null $limit The LIMIT clause value, if any
 * @property-read int|null $offset The OFFSET clause value, if any
 * @property-read Combine[] $combines An array of Combine clauses, if any
 */
class Select extends Statement
{
    use TableAwareTrait;
    use WhereAwareTrait;

    protected ?string $quantifier = null;

    /**
     * @var string[]|Identifier[]|Literal[]|Expression[]|self[]
     * @psalm-var array<string|int, string|Identifier|Literal|Expression|self>
     */
    protected array $columns = [];

    /** Used for SELECT INTO statements */
    protected ?string $into = null;

    /** @var string|self|null */
    protected $from = null;

    protected ?string $alias = null;

    /** @var Join[] */
    protected array $joins = [];

    protected array $groupBy = [];

    protected ?Having $having = null;

    protected array $orderBy = [];

    protected ?int $limit = null;

    protected ?int $offset = null;

    /** @var Combine[] */
    protected array $combines = [];

    /**
     * @todo Allow resetting of non defining properties?
     */
    protected $resettable_props = [
        'columns'  => [],
        'into'     => null,
        'joins'    => [],
        'where'    => null,
        'groupBy'  => [],
        'having'   => null,
        'orderBy'  => [],
        'limit'    => null,
        'offset'   => null,
        'combines' => [],
    ];

    /**
     * @param null|string|string[]|Expression|Expression[]|Identifier|Identifier[]|Literal|Literal[]|self|self[] $columns
     *      One or many column names, Identifiers, Literals, Expressions or sub-select statements
     * @psalm-param null|string|Expression|Identifier|Literal|self|array<int|string, string|Expression|Identifier|Literal|self> $columns
     * @param string|self|null $from A db-table name or a sub-select statement
     * @param string|null $alias
     */
    public function __construct($columns = null, $from = null, string $alias = null)
    {
        if (!empty($columns)) {
            if (is_array($columns)) {
                $this->columns($columns);
            } else {
                $this->column($columns);
            }
        }
        if (!empty($from)) {
            $this->from($from, $alias);
        }
    }

    /**
     * @param string $quantifier
     * @return $this Fluent interface
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
     * Set the DISTINCT clause
     *
     * @return $this Fluent interface
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
     * @param string[]|Expression[]|Identifier[]|Literal[]|self[] $columns
     * @psalm-param array<int|string, string|Expression|Identifier|Literal|self> $columns
     * @return $this Fluent interface
     */
    public function columns(array $columns): self
    {
        $this->columns = [];
        foreach ($columns as $key => $column) {
            $this->column($column, is_numeric($key) ? null : $key);
        }

        return $this;
    }

    /**
     * Add a column to the select list
     *
     * @param string|Identifier|Literal|Expression|self $column
     * @param string $alias
     * @return $this Fluent interface
     * @throws RuntimeException
     */
    public function column($column, string $alias = null): self
    {
        self::assertValidColumn($column, $alias);

        $key = $alias ?? (is_string($column) ? $column : null);

        if ($column instanceof ElementInterface) {
            if ($column === $this) {
                throw new RuntimeException(
                    "A sql select statement cannot add itself as a column!"
                );
            }
            if ($column->parentIsNot($this)) {
                $column = clone $column;
            }
            $column->setParent($this);
        }

        if (isset($key)) {
            $this->columns[$key] = $column;
        } else {
            $this->columns[] = $column;
        }

        $this->clearPartialSQL('columns');

        return $this;
    }

    /**
     * @param mixed $column
     * @param string $key
     *
     * @throws InvalidArgumentException
     */
    private static function assertValidColumn(&$column, string $key = null): void
    {
        if (is_string($column) && '' !== $column = trim($column)) {
            if (is_numeric($column)) {
                $column = new Literal($column);
            }
            return;
        }

        if ($column instanceof Identifier
            || $column instanceof Literal
            || $column instanceof Expression
            || $column instanceof self
        ) {
             return;
        }

        throw new InvalidArgumentException(sprintf(
            "A table column must be"
            . " a non empty string,"
            . " an Identifier,"
            . " a non empty Expression or Literal expression or"
            . " a Select statement,"
            . " `%s` provided%s!",
            is_object($column) ? get_class($column) : gettype($column),
            isset($key) ? " for index/column-alias `{$key}`" : ""
        ));
    }

    /**
     * Add a COUNT() aggregate column
     *
     * @param string $identifier
     * @param string $alias
     * @return $this Fluent interface
     */
    public function count(string $identifier = Sql::ASTERISK, string $alias = null): self
    {
        return $this->aggregate(Sql::COUNT, $identifier, $alias);
    }

    /**
     * Add a SUM() aggregate column
     *
     * @param string $identifier
     * @param string $alias
     * @return $this Fluent interface
     */
    public function sum(string $identifier, string $alias = null): self
    {
        return $this->aggregate(Sql::SUM, $identifier, $alias);
    }

    /**
     * Add a MIN() aggregate column
     *
     * @param string $identifier
     * @param string $alias
     * @return $this Fluent interface
     */
    public function min(string $identifier, string $alias = null): self
    {
        return $this->aggregate(Sql::MIN, $identifier, $alias);
    }

    /**
     * Add a MAX() aggregate column
     *
     * @param string $identifier
     * @param string $alias
     * @return $this Fluent interface
     */
    public function max(string $identifier, string $alias = null): self
    {
        return $this->aggregate(Sql::MAX, $identifier, $alias);
    }

    /**
     * Add an AVG() aggregate column
     *
     * @param string $identifier
     * @param string $alias
     * @return $this Fluent interface
     */
    public function avg(string $identifier, string $alias = null): self
    {
        return $this->aggregate(Sql::AVG, $identifier, $alias);
    }

    /**
     * Add an aggregate column with the specified SQL function
     *
     * @param string $sqlAggregateFunc
     * @param string $identifier
     * @param string $alias
     * @return $this Fluent interface
     */
    public function aggregate(string $sqlAggregateFunc, string $identifier, string $alias = null): self
    {
        return $this->column(new Literal("{$sqlAggregateFunc}({$identifier})"), $alias);
    }

    private function getColumnsSQL(DriverInterface $driver, Params $params): string
    {
        if (isset($this->sqls['columns'])
            && !$this->driver_changed
            && !$this->params_changed
        ) {
            return $this->sqls['columns'];
        }

        // We can only cache when there are no parameters to import, start with
        // true and set it to false when meeting parametric column
        $cache = true;

        // Overridden by driver?
        if ($driver instanceof SelectColumnsSqlProvider) {
            $sql = $driver->getSelectColumnsSQL($this, $params, $cache);
            if ($cache) {
                $this->sqls['columns'] = $sql;
            }
            return $sql;
        }

        $add_tb_prefix = !empty($this->joins) && !empty($this->table);

        if (empty($this->columns)) {
            $this->columns = [Sql::ASTERISK =>  Sql::ASTERISK];
        }

        $sqls = [];
        foreach ($this->columns as $key => $column) {
            if ($column === Sql::ASTERISK) {
                $prefix = !empty($this->alias) ? $driver->quoteAlias($this->alias) : null;
                if (empty($prefix) && $add_tb_prefix) {
                    $prefix = $driver->quoteIdentifier($this->table);
                }
                $sqls[] = !empty($prefix) ? ("{$prefix}." . Sql::ASTERISK) : Sql::ASTERISK;
                continue; // no-alias
            }

            if (is_string($column)) {
                $column_sql = $driver->quoteIdentifier(
                    $this->normalizeColumn($column, $driver, $add_tb_prefix)
                );
            } elseif ($column instanceof Identifier) {
                $column_sql = $column->getSQL($driver);
            } elseif ($column instanceof Literal) {
                $column_sql = $column->getSQL();
            } elseif ($column instanceof Expression) {
                $column_sql = $column->getSQL($driver, $params);
                $cache = $cache && 0 === count($column->substitutions);
            } elseif ($column instanceof self) {
                $column_sql = $column->getSQL($driver, $params);
                $cache = false;
            } else {
                // @codeCoverageIgnoreStart
                // Should be unreacheable
                throw new InvalidArgumentException(sprintf(
                    "Invalid db-table column type! Allowed types are: string, Literal,"
                    . " Expression, Select, `%s` provided!",
                    is_object($column) ? get_class($column) : gettype($column)
                ));
                // @codeCoverageIgnoreEnd
            }

            // Add alias?
            if (!is_numeric($key) && $key !== '' && $key !== $column) {
                $column_sql .= " AS " . $driver->quoteAlias($key);
            }

            $sqls[] = $column_sql;
        }

        $sql = trim(implode(", ", $sqls));
        if ($cache) {
            $this->sqls['columns'] = $sql;
        }

        return $sql;
    }

    /**
     * Prepend the statement primary-table alias or name if not already present
     *
     * @param string $column
     * @param DriverInterface $driver
     * @param bool $add_tb_prefix Add table prefix?
     * @return string
     */
    protected function normalizeColumn(string $column, DriverInterface $driver, bool $add_tb_prefix = false): string
    {
        // Unquote the column first
        $column = $driver->unquote($column);
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
     * @param string|self $from The db-table name to select from
     * @param string|null $alias The db-table alias, if any
     * @return $this Provides fluent interface
     * @throws RuntimeException
     */
    public function from($from, string $alias = null): self
    {
        if (isset($this->from) || isset($this->table)) {
            throw new RuntimeException(sprintf(
                "Cannot change the `from` property for this Select, from is already set to %s!",
                $this->from instanceof self ? "a sub-select" : "table `{$this->table}`"
            ));
        }

        self::assertValidFrom($from, $alias);

        if ($from instanceof self) {
            if ($from === $this) {
                throw new RuntimeException(
                    "A sql select statement cannot add itself as a FROM clause!"
                );
            }
            $this->from = $from->parentIsNot($this) ? clone $from : $from;
            $this->from->setParent($this);
        } else {
            $this->setTable($from);
        }

        if (!empty($alias)) {
            $this->alias = $alias;
        }

        return $this;
    }

    /**
     * Add a table name to insert the selected rows INTO
     *
     * @param string|null $into The INTO table name
     * @return $this Provides fluent interface
     * @throws InvalidArgumentException
     */
    public function into(?string $into): self
    {
        if ($into !== null) {
            $into = trim($into);
            if ('' === $into) {
                throw new InvalidArgumentException(
                    "A INTO table name cannot be an empty string!"
                );
            }
        }

        $this->into = $into;

        return $this;
    }

    /**
     * @param string|self $from
     * @param string $alias
     *
     * @throws InvalidArgumentException
     */
    private static function assertValidFrom($from, string &$alias = null): void
    {
        if (is_string($alias)) {
            $alias = trim($alias);
        }

        if (is_string($from)) {
            // OK, let TableAwareTrait assert its validity
            return;
        }

        if (! $from instanceof self) {
            throw new InvalidArgumentException(sprintf(
                "The FROM clause argument can be either"
                . " a table name or"
                . " a sub-select statement,"
                . " `%s` provided!",
                is_object($from) ? get_class($from) : gettype($from)
            ));
        }

        if (empty($alias)) {
            throw new InvalidArgumentException(
                "A FROM clause with a sub-select requires an alias!"
            );
        }
    }

    private function getIntoSQL(DriverInterface $driver): string
    {
        if ($this->into === null) {
            return '';
        }

        return  "INTO {$driver->quoteIdentifier($this->into)}";
    }

    private function getFromSQL(DriverInterface $driver, Params $params, bool $pretty = false): string
    {
        if (empty($this->from) && empty($this->table)) {
            throw new RuntimeException(
                "The FROM clause table or sub-select has not been defined!"
            );
        }

        if ($this->from instanceof self) {
            if ($pretty) {
                $nl = "\n";
                $indent = str_repeat(" ", $this->getNestingLevel() * 4);
            } else {
                $nl = $indent = "";
            }
            $from = "({$nl}"
                . $this->from->getSQL($driver, $params, $pretty)
                . "{$nl}{$indent})";
        } else {
            $from = $driver->quoteIdentifier($this->table);
        }

        if (!empty($this->alias)) {
            $from = "{$from} {$driver->quoteAlias($this->alias)}";
            $from = trim($from);
        }

        return "FROM {$from}";
    }

    /**
     * Add a join clause instance to this statement
     *
     * @param Join $join The join clause
     * @return $this Fluent interface
     */
    public function addJoin(Join $join): self
    {
        if ($join->parentIsNot($this)) {
            $join = clone $join;
        }

        $this->joins[] = $join;
        $join->setParent($this);

        $this->clearSQL();

        return $this;
    }

    /**
     * Add a JOIN clause
     *
     * @param string $table The joined table name
     * @param string $alias The joined table alias
     * @param On|Predicate|Predicate\Set|array|string|Literal|Identifier|null $specification
     * @param string $type The JOIN type
     * @return $this Fluent interface
     */
    public function join(string $table, string $alias, $specification = null, string $type = Sql::JOIN_AUTO): self
    {
        return $this->addJoin(
            new Join($type, $table, $alias, $specification)
        );
    }

    /**
     * Add an INNER JOIN clause
     *
     * @param string $table The joined table name
     * @param string $alias The joined table alias
     * @param On|Predicate|Predicate\Set|array|string|Literal|Identifier|null $specification
     * @return $this Fluent interface
     */
    public function innerJoin(string $table, string $alias, $specification = null): self
    {
        return $this->addJoin(
            new Join(Sql::JOIN_INNER, $table, $alias, $specification)
        );
    }

    /**
     * Add a LEFT [OUTER] JOIN clause
     *
     * @param string $table The joined table name
     * @param string $alias The joined table alias
     * @param On|Predicate|Predicate\Set|array|string|Literal|Identifier|null $specification
     * @return $this Fluent interface
     */
    public function leftJoin(string $table, string $alias, $specification = null): self
    {
        return $this->addJoin(
            new Join(Sql::JOIN_LEFT, $table, $alias, $specification)
        );
    }

    /**
     * Add a RIGHT [OUTER] JOIN clause
     *
     * @param string $table The joined table name
     * @param string $alias The joined table alias
     * @param On|Predicate|Predicate\Set|array|string|Literal|Identifier|null $specification
     * @return $this Fluent interface
     */
    public function rightJoin(string $table, string $alias, $specification = null): self
    {
        return $this->addJoin(
            new Join(Sql::JOIN_RIGHT, $table, $alias, $specification)
        );
    }

    /**
     * Add a FULL [OUTER] JOIN clause
     *
     * @param string $table The joined table name
     * @param string $alias The joined table alias
     * @param On|Predicate|Predicate\Set|array|string|Literal|Identifier|null $specification
     * @return $this Fluent interface
     */
    public function fullJoin(string $table, string $alias, $specification = null): self
    {
        return $this->addJoin(
            new Join(Sql::JOIN_FULL, $table, $alias, $specification)
        );
    }

    /**
     * Add a NATURAL JOIN clause
     *
     * @param string $table The joined table name
     * @param string $alias The joined table alias
     * @param On|Predicate|Predicate\Set|array|string|Literal|Identifier|null $specification
     * @return $this Fluent interface
     */
    public function naturalJoin(string $table, string $alias, $specification = null): self
    {
        return $this->addJoin(
            new Join(Sql::JOIN_NATURAL, $table, $alias, $specification)
        );
    }

    /**
     * Add a NATURAL INNER JOIN clause
     *
     * @param string $table The joined table name
     * @param string $alias The joined table alias
     * @param On|Predicate|Predicate\Set|array|string|Literal|Identifier|null $specification
     * @return $this Fluent interface
     */
    public function naturalInnerJoin(string $table, string $alias, $specification = null): self
    {
        return $this->addJoin(
            new Join(Sql::JOIN_NATURAL_INNER, $table, $alias, $specification)
        );
    }

    /**
     * Add a NATURAL LEFT  [OUTER]JOIN clause
     *
     * @param string $table The joined table name
     * @param string $alias The joined table alias
     * @param On|Predicate|Predicate\Set|array|string|Literal|Identifier|null $specification
     * @return $this Fluent interface
     */
    public function naturalLeftJoin(string $table, string $alias, $specification = null): self
    {
        return $this->addJoin(
            new Join(Sql::JOIN_NATURAL_LEFT, $table, $alias, $specification)
        );
    }

    /**
     * Add a NATURAL RIGHT [OUTER] JOIN clause
     *
     * @param string $table The joined table name
     * @param string $alias The joined table alias
     * @param On|Predicate|Predicate\Set|array|string|Literal|Identifier|null $specification
     * @return $this Fluent interface
     */
    public function naturalRightJoin(string $table, string $alias, $specification = null): self
    {
        return $this->addJoin(
            new Join(Sql::JOIN_NATURAL_RIGHT, $table, $alias, $specification)
        );
    }

    /**
     * Add a NATURAL FULL [OUTER] JOIN clause
     *
     * @param string $table The joined table name
     * @param string $alias The joined table alias
     * @param On|Predicate|Predicate\Set|array|string|Literal|Identifier|null $specification
     * @return $this Fluent interface
     */
    public function naturalFullJoin(string $table, string $alias, $specification = null): self
    {
        return $this->addJoin(
            new Join(Sql::JOIN_NATURAL_FULL, $table, $alias, $specification)
        );
    }

    /**
     * Add a CROSS JOIN clause
     *
     * @param string $table The joined table name
     * @param string $alias The joined table alias
     * @param On|Predicate|Predicate\Set|array|string|Literal|Identifier|null $specification
     * @return $this Fluent interface
     */
    public function crossJoin(string $table, string $alias, $specification = null): self
    {
        return $this->addJoin(
            new Join(Sql::JOIN_CROSS, $table, $alias, $specification)
        );
    }

    /**
     * Add a UNION JOIN clause
     *
     * @param string $table The joined table name
     * @param string $alias The joined table alias
     * @param On|Predicate|Predicate\Set|array|string|Literal|Identifier|null $specification
     * @return $this Fluent interface
     */
    public function unionJoin(string $table, string $alias, $specification = null): self
    {
        return $this->addJoin(
            new Join(Sql::JOIN_UNION, $table, $alias, $specification)
        );
    }

    private function getJoinSQL(DriverInterface $driver, Params $params): string
    {
        if (empty($this->joins)) {
            return '';
        }

        $sqls = [];
        foreach ($this->joins as $join) {
            $sqls[] = $join->getSQL($driver, $params);
        }

        return trim(implode(" ", $sqls));
    }

    /**
     * Add or set the GROUP BY clause elements
     *
     * @param string|string[]|Literal|Literal[] $groupBy
     * @param bool $replace
     * @return $this Fluent interface
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

        self::assertValidIdentifier($groupBy, 'select group-by ');

        $this->groupBy[] = $groupBy;

        $this->clearPartialSQL('group');

        return $this;
    }

    private function getGroupBySQL(DriverInterface $driver): string
    {
        if (empty($this->groupBy)) {
            return '';
        }

        // Partial caching is possibile as there are no params to import here
        if (isset($this->sqls['group']) && !$this->driver_changed) {
            return $this->sqls['group'];
        }

        $groupBy = $this->groupBy;
        foreach ($groupBy as $key => $identifier) {
            $groupBy[$key] = $this->getIdentifierSQL($identifier, $driver);
        }

        $grouping_element_list = implode(", ", $groupBy);

        $this->sqls['group'] = $sql = "GROUP BY {$grouping_element_list}";
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
            if (!isset($this->having)) {
                $this->having = new Having();
                $this->having->setParent($this);
            }
            $having($this->having);
            return $this;
        }

        $this->setConditionalClause('having', Having::class, $having);
        return $this;
    }

    private function getHavingSQL(DriverInterface $driver, Params $params): string
    {
        return $this->getConditionalClauseSQL('having', $driver, $params);
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

        self::assertValidIdentifier($orderBy, 'select order-by ');

        if (is_string($sortdir_or_replace)) {
            $sortdir = strtoupper($sortdir_or_replace) === Sql::DESC
                ? Sql::DESC
                : Sql::ASC;
        } else {
            $sortdir = Sql::ASC;
        }

        // We need to transform the sql-element information to a string
        if ($orderBy instanceof Element) {
            if ($orderBy instanceof Alias) {
                $orderBy = Alias::class . "::{$orderBy->alias}";
            } elseif ($orderBy instanceof Identifier) {
                $orderBy = Identifier::class . "::{$orderBy->identifier}";
            } elseif ($orderBy instanceof Literal) {
                $orderBy = Literal::class . "::{$orderBy->literal}";
            }
        }

        $this->orderBy[$orderBy] = $sortdir;

        $this->clearPartialSQL('order');

        return $this;
    }

    private function getOrderBySQL(DriverInterface $driver): string
    {
        if (empty($this->orderBy)) {
            return '';
        }

        // Partial caching is possibile as there are no params to import here
        if (isset($this->sqls['order']) && !$this->driver_changed) {
            return $this->sqls['order'];
        }

        $sqls = [];
        foreach ($this->orderBy as $identifier => $direction) {
            // Rebuild the original identifier if it was a sql-element
            $parts = explode('::', $identifier);
            if (isset($parts[1])) {
                $fqcn = $parts[0];
                $expr = $parts[1];
                $identifier = new $fqcn($expr);
            }
            $sqls[] = $this->getIdentifierSQL($identifier, $driver) . " {$direction}";
        }

        $sort_specification_list = implode(", ", $sqls);

        $this->sqls['order'] = $sql = "ORDER BY {$sort_specification_list}";
        return $sql;
    }

    public function limit(?int $limit): self
    {
        if ($limit < 0) {
            $limit = null;
        }

        // No change? Avoid clearing the cache
        if ($limit === $this->limit) {
            return $this;
        }

        $this->limit = $limit;
        $this->clearSQL();

        return $this;
    }

    public function offset(?int $offset): self
    {
        if ($offset <= 0) {
            $offset = null;
        }

        // No change? Avoid clearing the cache
        if ($offset === $this->offset) {
            return $this;
        }

        $this->offset = $offset;
        $this->clearSQL();

        return $this;
    }

    private function getLimitSQL(DriverInterface $driver, Params $params): string
    {
        if (!isset($this->limit) && (int)$this->offset === 0) {
            return '';
        }

        // Computed by driver?
        if ($driver instanceof LimitSqlProvider) {
            return $driver->getLimitSQL($this, $params);
        }

        // Default implementation working for MySQL, PostgreSQL and Sqlite
        // PostgreSQL also supports OFFSET without LIMIT
        if (isset($this->limit)) {
            $limit = $params->create($this->limit, PDO::PARAM_INT, 'limit');
            $sql = "LIMIT {$limit}";
        }

        if (isset($this->offset) && $this->offset > 0) {
            if (!isset($sql)) {
                $sql = "LIMIT " . PHP_INT_MAX;
            }
            $offset = $params->create($this->offset, PDO::PARAM_INT, 'offset');
            $sql .= " OFFSET {$offset}";
        }

        return $sql ?? '';
    }

    public function union(self $select, bool $all = false): self
    {
        return $this->combine(Sql::UNION, $select, $all);
    }

    public function intersect(self $select, bool $all = false): self
    {
        return $this->combine(Sql::INTERSECT, $select, $all);
    }

    public function except(self $select, bool $all = false): self
    {
        return $this->combine(Sql::EXCEPT, $select, $all);
    }

    private function combine(string $type, self $select, bool $all = false): self
    {
        $type = strtoupper($type);

        if ($select === $this) {
            throw new InvalidArgumentException(
                "A sql select statement cannot use itself for a/an {$type} clause!"
            );
        }

        $combine = Combine::create($type, $select, $all);
        $combine->setParent($this);

        $this->combines[] = $combine;

        $this->clearSQL();

        return $this;
    }

    private function getCombineSQL(DriverInterface $driver, Params $params, bool $pretty = false): string
    {
        if (empty($this->combines)) {
            return '';
        }

        $sqls = [];
        foreach ($this->combines as $combine) {
            $sqls[] = $combine->getSQL($driver, $params, $pretty);
        }

        return implode($pretty ? "\n" :  " ", $sqls);
    }

    /**
     * @see Sql\ElementInterface::getSQL()
     *
     * @param bool $pretty Return a nicely formatted sql string?
     */
    public function getSQL(DriverInterface $driver = null, Params $params = null, bool $pretty = false): string
    {
        if ($this->hasValidSqlCache($driver, $params)) {
            return $this->sql;
        }

        $this->driver = $driver; // Set last used driver argument
        $this->params = null; // Reset previously collected params, if any

        $driver = $driver ?? Driver::ansi();
        $params = $params ?? ($this->params = new Params());

        if ($driver instanceof SelectSqlDecorator) {
            $this->sql = $driver->decorateSelectSQL($this, $params, $pretty);
        } elseif ($driver instanceof SelectDecorator) {
            // The original parent may be changed by the decorator returning a different instance
            $parent = $this->parent;
            $select = $driver->decorateSelect($this, $params);
            if (isset($parent)) {
                $select->setParent($parent);
            }
            $this->sql = $select->generateSQL($driver, $params, $pretty);
        } else {
            // Generate and cache a fresh sql string
            $this->sql = $this->generateSQL($driver, $params, $pretty);
        }

        return $this->sql;
    }

    /**
     * Generate a fresh SQL string (triggers parameters import)
     *
     * Also used by SelectSqlDecorator drivers to keep imported parameters in the
     * same order of appearance in the final sql statement string
     *
     * @param DriverInterface $driver
     * @param Params $params
     * @return string
     */
    protected function generateSQL(DriverInterface $driver, Params $params, bool $pretty = false): string
    {
        $select = Sql::SELECT;
        if (!empty($this->quantifier)) {
            $select .= " {$this->quantifier}";
        }

        $sqls = [
            "{$select} {$this->getColumnsSQL($driver, $params)}",
        ];

        $sqls[] = $this->getIntoSQL($driver);
        $sqls[] = $this->getFromSQL($driver, $params, $pretty);
        $sqls[] = $this->getJoinSQL($driver, $params);
        $sqls[] = $this->getWhereSQL($driver, $params);
        $sqls[] = $this->getGroupBySQL($driver);
        $sqls[] = $this->getHavingSQL($driver, $params);
        $sqls[] = $this->getCombineSQL($driver, $params, $pretty);
        $sqls[] = $this->getOrderBySQL($driver);
        $sqls[] = $this->getLimitSQL($driver, $params);

        foreach ($sqls as $i => $sql) {
            if (self::isEmptySQL($sql)) {
                unset($sqls[$i]);
            }
        }

        if ($pretty) {
            $indent = str_repeat(" ", $this->getNestingLevel() * 4);
            $sql = $indent . implode("\n{$indent}", $sqls);
        } else {
            $sql = implode(" ", $sqls);
        }

        $sql = $this->quoteTableNames($sql, $driver);
        $sql = $this->quoteTableAliases($sql, $driver);

        return $sql;
    }

    private function quoteTableAliases(string $sql, DriverInterface $driver): string
    {
        $tb_aliases = [];
        if (!empty($this->alias)) {
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
            $search[] = "/(^|\(|\s)" . preg_quote($tb_alias) . "\./";
            $replace[] = '\1' . "{$driver->quoteAlias($tb_alias)}.";
        }

        return preg_replace($search, $replace, $sql) ?? $sql;
    }

    private function quoteTableNames(string $sql, DriverInterface $driver): string
    {
        $tb_names = [];

        if (!empty($this->table)) {
            $tb_names[] = $this->table;
        }

        foreach ($this->joins as $join) {
            $tb_names[] = $join->table;
        }

        if (empty($tb_names)) {
            return $sql;
        }

        $search = $replace = [];
        foreach ($tb_names as $tb_name) {
            $search[] = "/(^|\(|\s)" . preg_quote($tb_name) . "\./";
            $replace[] = '\1' . "{$driver->quoteIdentifier($tb_name)}.";
        }

        return preg_replace($search, $replace, $sql) ?? $sql;
    }

    private function getNestingLevel(): int
    {
        $level = 0;
        $select = $this;
        while ($select->parent instanceof self) {
            $select = $select->parent;
            $level += 1;
        }

        return $level;
    }

    /**
     * @return mixed
     */
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

        if ('into' === $name) {
            return $this->into;
        }

        if ('from' === $name) {
            return $this->from ?? $this->table;
        }

        if ('where' === $name) {
            if ($this->where === null) {
                $this->where = new Where();
                $this->where->setParent($this);
            }
            return $this->where;
        }

        if ('joins' === $name) {
            return $this->joins;
        }

        if ('having' === $name) {
            if ($this->having === null) {
                $this->having = new Having();
                $this->having->setParent($this);
            }
            return $this->having;
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

        if ('combines' === $name) {
            return $this->combines;
        }

        return parent::__get($name);
    }

    public function __clone()
    {
        parent::__clone();
        foreach ($this->columns as $key => $column) {
            if ($column instanceof ElementInterface) {
                $this->columns[$key] = $column = clone $column;
                $column->setParent($this);
            }
        }
        if ($this->from instanceof self) {
            $this->from = clone $this->from;
            $this->from->setParent($this);
        }
        if (!empty($this->joins)) {
            foreach ($this->joins as $k => $join) {
                $this->joins[$k] = $join = clone $join;
                $join->setParent($this);
            }
        }
        if ($this->where instanceof Where) {
            $this->where = clone $this->where;
            $this->where->setParent($this);
        }
        if ($this->having instanceof Having) {
            $this->having = clone $this->having;
            $this->having->setParent($this);
        }
        if (!empty($this->combines)) {
            foreach ($this->combines as $k => $combine) {
                $this->combines[$k] = $combine = clone $combine;
                $combine->setParent($this);
            }
        }
    }
}
