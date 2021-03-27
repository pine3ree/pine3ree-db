<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Command;

use Closure;
use P3\Db\Exception\InvalidArgumentException;
use P3\Db\Command;
use P3\Db\Command\Reader as ReaderInterface;
use P3\Db\Command\Traits\Reader as ReaderTrait;
use P3\Db\Db;
use P3\Db\Sql;
use P3\Db\Sql\Clause\Join;
use P3\Db\Sql\Clause\Having;
use P3\Db\Sql\Clause\On;
use P3\Db\Sql\Clause\Where;
use P3\Db\Sql\Expression;
use P3\Db\Sql\Identifier;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Predicate;
use P3\Db\Sql\Statement\Select as SqlSelect;
use PDO;
use P3\Db\Exception\RuntimeException;

use function func_get_args;
use function is_array;

/**
 * Class Select
 *
 * @property-read SqlSelect $sqlStatement
 * @property-read string|null $table The db table to select from if already set
 * @property-read string|Null $alias The table alias if any
 * @property-read string|null $quantifier The SELECT quantifier if any
 * @property-read string[] $columns The columns to be returned
 * @property-read string|self|null $from The db table to select from or a sub-select if already set
 * @property-read Where $where The Where clause, built on-first-access if null
 * @property-read Join[] $joins An array of Join clauses if any
 * @property-read array[] $groupBy An array of GROUP BY identifiers
 * @property-read Having $having The Having clause, built on-first-access if null
 * @property-read string[] $orderBy An array of ORDER BY identifier to sort-direction pairs
 * @psalm-property-read array<string, string> $orderBy An array of ORDER BY identifier to sort-direction pairs
 * @property-read int|null $limit The Having clause if any
 * @property-read int|null $offset The Having clause if any
 * @property-read self|null $union The sql-select statement for the UNION clause, if any
 * @property-read bool|null $unionAll Is it a UNION ALL clause?
 * @property-read self|null $intersect The sql-select statement for the INTERSECT clause, if any
 */
class Select extends Command implements ReaderInterface
{
    use ReaderTrait;

    /** @var string|null */
    protected $indexBy;

    /**
     * @param Db $db
     * @param null|string|string[]|Expression|Expression[]|Identifier|Identifier[]|Literal|Literal[]|SqlSelect|SqlSelect[] $columns
     *      One or many column names, Literal expressions or sub-select statements
     * @psalm-param null|string|Expression|Identifier|Literal|SqlSelect|array<int|string, string|Expression|Identifier|Literal|SqlSelect> $columns
     * @param string|SqlSelect|null $from The db-table name or a sub-select statement
     * @param string|null $alias
     */
    public function __construct(Db $db, $columns = null, $from = null, string $alias = null)
    {
        parent::__construct($db, new SqlSelect($columns, $from, $alias));
    }

    /**
     * @see SqlSelect::quantifier()
     * @return $this Fluent interface
     */
    public function quantifier(string $quantifier): self
    {
        $this->sqlStatement->quantifier($quantifier);
        return $this;
    }

    /**
     * @see SqlSelect::distinct()
     * @return $this Fluent interface
     */
    public function distinct(): self
    {
        $this->sqlStatement->distinct();
        return $this;
    }

    /**
     * @see SqlSelect::columns()
     *
     * @param string[]|Expression[]|Identifier[]|Literal[]|SqlSelect[] $columns
     * @psalm-param array<int|string, string|Expression|Identifier|Literal|SqlSelect> $columns
     * @return $this Fluent interface
     */
    public function columns($columns): self
    {
        $this->sqlStatement->columns($columns);
        return $this;
    }

    /**
     * @see SqlSelect::column()
     *
     * @param string|Identifier|Literal|Expression|SqlSelect $column
     * @param string $alias
     * @return $this Fluent interface
     */
    public function column($column, string $alias = null): self
    {
        $this->sqlStatement->column($column, $alias);
        return $this;
    }

    /**
     * @see SqlSelect::count()
     *
     * @param string $identifier
     * @param string $alias
     * @return $this Fluent interface
     */
    public function count($identifier = Sql::ASTERISK, string $alias = null): self
    {
        $this->sqlStatement->count($identifier, $alias);
        return $this;
    }

    /**
     * @see SqlSelect::sum()
     *
     * @param string $identifier
     * @param string $alias
     * @return $this Fluent interface
     */
    public function sum($identifier, string $alias = null): self
    {
        $this->sqlStatement->sum($identifier, $alias);
        return $this;
    }

    /**
     * @see SqlSelect::min()
     *
     * @param string $identifier
     * @param string $alias
     * @return $this Fluent interface
     */
    public function min(string $identifier, string $alias = null): self
    {
        $this->sqlStatement->min($identifier, $alias);
        return $this;
    }

    /**
     * @see SqlSelect::max()
     *
     * @param string $identifier
     * @param string $alias
     * @return $this Fluent interface
     */
    public function max(string $identifier, string $alias = null): self
    {
        $this->sqlStatement->max($identifier, $alias);
        return $this;
    }

    /**
     * @see SqlSelect::avg()
     *
     * @param string $identifier
     * @param string $alias
     * @return $this Fluent interface
     */
    public function avg(string $identifier, string $alias = null): self
    {
        $this->sqlStatement->avg($identifier, $alias);
        return $this;
    }

    /**
     * @see SqlSelect::aggregate()
     *
     * @param string $sqlAggregateFunc
     * @param string $identifier
     * @param string $alias
     * @return $this Fluent interface
     */
    public function aggregate(string $sqlAggregateFunc, string $identifier, string $alias = null): self
    {
        $this->sqlStatement->aggregate($sqlAggregateFunc, $identifier, $alias);
        return $this;
    }

    /**
     * @see SqlSelect::from()
     *
     * @param string|SqlSelect $from The db-table name to select from
     * @param string|null $alias The db-table alias, if any
     * @return $this Fluent interface
     */
    public function from($from, string $alias = null): self
    {
        $this->sqlStatement->from($from, $alias);
        return $this;
    }

    /**
     * @see SqlSelect::addJoin()
     *
     * @param Join $join The join clause
     * @return $this Fluent interface
     */
    public function addJoin(Join $join): self
    {
        $this->sqlStatement->addJoin($join);
        return $this;
    }

    /**
     * @see SqlSelect::join()
     *
     * @param string $table The joined table name
     * @param string $alias The joined table alias
     * @param On|Predicate|Predicate\Set|array|string|Literal|Identifier|null $specification
     * @param string $type The JOIN type
     * @return $this Fluent interface
     */
    public function join(string $table, string $alias, $specification, string $type = Sql::JOIN_AUTO): self
    {
        $this->sqlStatement->join($table, $alias, $specification, $type);
        return $this;
    }

    /**
     * @see SqlSelect::innerJoin()
     *
     * @param string $table The joined table name
     * @param string $alias The joined table alias
     * @param On|Predicate|Predicate\Set|array|string|Literal|Identifier|null $specification
     * @return $this Fluent interface
     */
    public function innerJoin(string $table, string $alias, $specification): self
    {
        $this->sqlStatement->innerJoin($table, $alias, $specification);
        return $this;
    }

    /**
     * @see SqlSelect::leftJoin()
     *
     * @param string $table The joined table name
     * @param string $alias The joined table alias
     * @param On|Predicate|Predicate\Set|array|string|Literal|Identifier|null $specification
     * @return $this Fluent interface
     */
    public function leftJoin(string $table, string $alias, $specification): self
    {
        $this->sqlStatement->leftJoin($table, $alias, $specification);
        return $this;
    }

    /**
     * @see SqlSelect::rightJoin()
     *
     * @param string $table The joined table name
     * @param string $alias The joined table alias
     * @param On|Predicate|Predicate\Set|array|string|Literal|Identifier|null $specification
     * @return $this Fluent interface
     */
    public function rightJoin(string $table, string $alias, $specification): self
    {
        $this->sqlStatement->rightJoin($table, $alias, $specification);
        return $this;
    }

    /**
     * @see SqlSelect::naturalJoin()
     *
     * @param string $table The joined table name
     * @param string $alias The joined table alias
     * @param On|Predicate|Predicate\Set|array|string|Literal|Identifier|null $specification
     * @return $this Fluent interface
     */
    public function naturalJoin(string $table, string $alias, $specification = null): self
    {
        $this->sqlStatement->naturalJoin($table, $alias, $specification);
        return $this;
    }

    /**
     * @see SqlSelect::naturalLeftJoin()
     *
     * @param string $table The joined table name
     * @param string $alias The joined table alias
     * @param On|Predicate|Predicate\Set|array|string|Literal|Identifier|null $specification
     * @return $this Fluent interface
     */
    public function naturalLeftJoin(string $table, string $alias, $specification = null): self
    {
        $this->sqlStatement->naturalLeftJoin($table, $alias, $specification);
        return $this;
    }

    /**
     * @see SqlSelect::naturalRightJoin()
     *
     * @param string $table The joined table name
     * @param string $alias The joined table alias
     * @param On|Predicate|Predicate\Set|array|string|Literal|Identifier|null $specification
     * @return $this Fluent interface
     */
    public function naturalRightJoin(string $table, string $alias, $specification = null): self
    {
        $this->sqlStatement->naturalRightJoin($table, $alias, $specification);
        return $this;
    }

    /**
     * @see SqlSelect::crossJoin()
     *
     * @param string $table The joined table name
     * @param string $alias The joined table alias
     * @param On|Predicate|Predicate\Set|array|string|Literal|Identifier|null $specification
     * @return $this Fluent interface
     */
    public function crossJoin(string $table, string $alias, $specification = null): self
    {
        $this->sqlStatement->crossJoin($table, $alias, $specification);
        return $this;
    }

    /**
     * @see SqlSelect::straightJoin()
     *
     * @param string $table The joined table name
     * @param string $alias The joined table alias
     * @param On|Predicate|Predicate\Set|array|string|Literal|Identifier|null $specification
     * @return $this Fluent interface
     */
    public function straightJoin(string $table, string $alias, $specification = null): self
    {
        $this->sqlStatement->straightJoin($table, $alias, $specification);
        return $this;
    }

    /**
     * @see SqlSelect::where()
     *
     * @param string|array|Predicate|Closure|Where $where
     * @return $this Fluent interface
     */
    public function where($where): self
    {
        $this->sqlStatement->where($where);
        return $this;
    }

    /**
     * @see SqlSelect::groupBy()
     *
     * @param string|string[]|Literal|Literal[] $groupBy
     * @param bool $replace
     * @return $this Fluent interface
     */
    public function groupBy($groupBy, bool $replace = false): self
    {
        $this->sqlStatement->groupBy($groupBy, $replace);
        return $this;
    }

    /**
     * @see SqlSelect::having()
     *
     * @param string|array|Predicate|Closure|Having $having
     * @return $this Fluent interface
     */
    public function having($having): self
    {
        $this->sqlStatement->having($having);
        return $this;
    }

    /**
     * @see SqlSelect::orderBy()
     *
     * @param string|array $orderBy
     * @param null|string|true $sortdir_or_replace Set the default sort direction or the replace flag
     * @return $this Fluent interface
     */
    public function orderBy($orderBy, $sortdir_or_replace = null): self
    {
        $this->sqlStatement->orderBy($orderBy, $sortdir_or_replace);
        return $this;
    }

    /**
     * @see SqlSelect::limit()
     * @return $this Fluent interface
     */
    public function limit(?int $limit): self
    {
        $this->sqlStatement->limit($limit);
        return $this;
    }

    /**
     * @see SqlSelect::offset()
     * @return $this Fluent interface
     */
    public function offset(?int $offset): self
    {
        $this->sqlStatement->offset($offset);
        return $this;
    }

    /**
     * @see SqlSelect::union()
     * @return $this Fluent interface
     */
    public function union(SqlSelect $select, bool $all = false): self
    {
        $this->sqlStatement->union($select, $all);
        return $this;
    }

    /**
     * @see SqlSelect::intersect()
     * @return $this Fluent interface
     */
    public function intersect(SqlSelect $select): self
    {
        $this->sqlStatement->intersect($select);
        return $this;
    }

    /**
     * Index the result by the given identifier
     *
     * @var string $identifier
     * @return $this Fluent interface
     */
    public function indexBy(string $identifier): self
    {
        $this->indexBy = $identifier;
        return $this;
    }

    /**
     * Fetch all the rows resulting by executing the composed sql-statement
     *
     * @see \PDOStatement::fetchAll()
     *
     * @param int $fetch_mode The PDO fetch style
     * @param mixed $fetch_argument Different meaning depending on the value of the fetch_style parameter
     * @param array $ctor_args Arguments of custom class constructor when the fetch_style parameter is PDO::FETCH_CLASS
     * @return array[]
     * @psalm-return array<string|int, mixed>[]
     * @throws RuntimeException
     */
    public function fetchAll(
        int $fetch_mode = PDO::FETCH_ASSOC,
        $fetch_argument = null,
        array $ctor_args = []
    ): array {
        if (false === $stmt = $this->execute()) {
            return [];
        }

        $args = func_get_args();

        $rows = empty($args)
            ? $stmt->fetchAll($fetch_mode)
            : $stmt->fetchAll(...$args);

        $stmt->closeCursor();

        if (empty($rows) || !is_array($rows)) {
            return [];
        }

        $indexBy = $this->indexBy;
        if (empty($indexBy)) {
            return $rows;
        }

        $indexed = [];
        switch ($fetch_mode) {
            case PDO::FETCH_ASSOC:
            case PDO::FETCH_BOTH:
            case PDO::FETCH_NUM:
                foreach ($rows as $i => $row) {
                    $index = $row[$indexBy] ?? null;
                    if (!isset($index)) {
                        throw new RuntimeException(
                            "The indexBy-identifier `{$indexBy}` is not a valid key in"
                            . " the result row with index={$i}!"
                        );
                    }
                    $indexed[$index] = $row;
                }
                return $indexed;

            case PDO::FETCH_CLASS:
            case PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE:
            case PDO::FETCH_OBJ:
                foreach ($rows as $i => $obj) {
                    $index = $obj->{$indexBy} ?? null;
                    if (!isset($index)) {
                        throw new RuntimeException(
                            "The indexBy-identifier `{$indexBy}` is not a valid property"
                            . " in the result object with index={$i}!"
                        );
                    }
                    $indexed[$index] = $obj;
                }
                return $indexed;
        }

        // @codeCoverageIgnoreStart
        throw new InvalidArgumentException(
            "Invalid fetch_mode combination pipe `{$fetch_mode}` for indexed-rowset!"
        );
        // @codeCoverageIgnoreEnd
    }

    /**
     * Fetch the first row, if any, after executing the composed sql statement
     *
     * @see \PDOStatement::fetch()
     *
     * @param int $fetch_mode The PDO fetch style
     * @param mixed $class_or_object Used with PDO::FETCH_CLASS and PDO::FETCH_INTO
     * @param array $ctor_args Arguments of custom class constructor when the fetch_style parameter is PDO::FETCH_CLASS
     * @return array|object|null
     */
    public function fetchOne(
        int $fetch_mode = PDO::FETCH_ASSOC,
        $class_or_object = null,
        array $ctor_args = []
    ) {
        $this->sqlStatement->limit(1);
        if (false === $stmt = $this->execute()) {
            return null;
        }

        switch ($fetch_mode) {
            case PDO::FETCH_ASSOC:
            case PDO::FETCH_BOTH:
            case PDO::FETCH_NUM:
                $row = $stmt->fetch($fetch_mode);
                $stmt->closeCursor();
                return is_array($row) ? $row : null;

            case PDO::FETCH_OBJ:
            case PDO::FETCH_LAZY:
                $obj = $stmt->fetch($fetch_mode);
                $stmt->closeCursor();
                return is_object($obj) ? $obj : null;

            case PDO::FETCH_CLASS:
            case PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE:
                if (!is_string($class_or_object)) {
                    throw new InvalidArgumentException(sprintf(
                        "\$class_or_object MUST be an FQCN string when fetch_mode"
                        . " = `PDO::FETCH_CLASS`, `%s` provided!",
                        gettype($class_or_object)
                    ));
                }
                $stmt->setFetchMode($fetch_mode, $class_or_object, $ctor_args);
                $object = $stmt->fetch();
                $stmt->closeCursor();
                return is_object($object) ? $object : null;

            case PDO::FETCH_INTO:
                if (!is_object($class_or_object)) {
                    throw new InvalidArgumentException(sprintf(
                        "\$class_or_object MUST be an object when fetch_mode"
                        . " = `PDO::FETCH_INTO`, `%s` provided!",
                        gettype($class_or_object)
                    ));
                }
                $obj = $class_or_object;
                $obj = $stmt->fetch($fetch_mode);
                return is_object($obj) ? $obj : null;
        }

        // @codeCoverageIgnoreStart
        throw new InvalidArgumentException(
            "Invalid fetch_mode combination pipe `{$fetch_mode}`!"
        );
        // @codeCoverageIgnoreEnd
    }

    /**
     * Fetch a column of the first row, if any, after executing the composed sql statement
     *
     * @return null|string
     */
    public function fetchScalar(string $identifier): ?string
    {
        $this->sqlStatement->limit(1);
        if (false === $stmt = $this->execute()) {
            return null;
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $row[$identifier] ?? null;
    }

    /**
     * Fetch the first column value of the first row, if any, after executing the composed sql statement
     *
     * @param int $column_number The column number to fetch the value from
     * @return mixed
     */
    public function fetchColumn(int $column_number = 0)
    {
        $this->sqlStatement->limit(1);
        if (false === $stmt = $this->execute()) {
            return null;
        }

        $value = $stmt->fetchColumn($column_number);
        $stmt->closeCursor();

        return $value;
    }
}
