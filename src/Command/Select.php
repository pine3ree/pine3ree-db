<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Command;

use InvalidArgumentException;
use P3\Db\Command;
use P3\Db\Command\Traits\Reader;
use P3\Db\Db;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Statement\Select as SqlSelect;
use PDO;
use RuntimeException;

use function func_get_args;
use function is_array;

/**
 * Class Select
 *
 * @property-read SqlSelect $statement
 */
class Select extends Command
{
    use Reader;

    /** @var string|null */
    protected $indexBy;

    /**
     *
     * @param Db $db
     * @param string|string[]|Literal|Literal[]|SqlSelect|SqlSelect[] $columns
     * @param string!SqlSelect|null $from The db-table name or a sub-select statement
     * @param string|null $alias
     */
    public function __construct(Db $db, $columns = null, $from = null, string $alias = null)
    {
        parent::__construct($db, new SqlSelect($columns, $from, $alias));
    }

    /**
     * @see SqlSelect::quantifier()
     * @return $this
     */
    public function quantifier(string $quantifier): self
    {
        $this->statement->quantifier($quantifier);
        return $this;
    }

    /**
     * @see SqlSelect::distinct()
     * @return $this
     */
    public function distinct(): self
    {
        $this->statement->distinct();
        return $this;
    }

    /**
     * @see SqlSelect::columns()
     * @return $this
     */
    public function columns($columns): self
    {
        $this->statement->columns($columns);
        return $this;
    }

    /**
     * @see SqlSelect::from()
     * @return $this
     */
    public function from($from, string $alias = null): self
    {
        $this->statement->from($from, $alias);
        return $this;
    }

    /**
     * @see SqlSelect::join()
     * @return $this
     */
    public function join(string $table, string $alias, $specification): self
    {
        $this->statement->join($table, $alias, $specification);
        return $this;
    }

    /**
     * @see SqlSelect::innerJoin()
     * @return $this
     */
    public function innerJoin(string $table, string $alias, $specification): self
    {
        $this->statement->innerJoin($table, $alias, $specification);
        return $this;
    }

    /**
     * @see SqlSelect::leftJoin()
     * @return $this
     */
    public function leftJoin(string $table, string $alias, $specification): self
    {
        $this->statement->leftJoin($table, $alias, $specification);
        return $this;
    }

    /**
     * @see SqlSelect::rightJoin()
     * @return $this
     */
    public function rightJoin(string $table, string $alias, $specification): self
    {
        $this->statement->rightJoin($table, $alias, $specification);
        return $this;
    }

    /**
     * @see self::addJoin()
     */
    public function naturalJoin(string $table, string $alias, $specification = null): self
    {
        $this->statement->naturalJoin($table, $alias, $specification);
        return $this;
    }

    /**
     * @see self::addJoin()
     */
    public function naturalLeftJoin(string $table, string $alias, $specification = null): self
    {
        $this->statement->naturalLeftJoin($table, $alias, $specification);
        return $this;
    }

    /**
     * @see self::addJoin()
     */
    public function naturalRightJoin(string $table, string $alias, $specification = null): self
    {
        $this->statement->naturalRightJoin($table, $alias, $specification);
        return $this;
    }

    /**
     * @see self::addJoin()
     */
    public function crossJoin(string $table, string $alias, $specification = null): self
    {
        $this->statement->crossJoin($table, $alias, $specification);
        return $this;
    }

    /**
     * @see self::addJoin()
     */
    public function straightJoin(string $table, string $alias, $specification = null): self
    {
        return $this->straightJoin($table, $alias, $specification);
        return $this;
    }

    /**
     * @see SqlSelect::where()
     * @return $this
     */
    public function where($where): self
    {
        $this->statement->where($where);
        return $this;
    }

    /**
     * @see SqlSelect::groupBy()
     * @return $this
     */
    public function groupBy($groupBy, bool $replace = false): self
    {
        $this->statement->groupBy($groupBy, $replace);
        return $this;
    }

    /**
     * @see SqlSelect::having()
     * @return $this
     */
    public function having($having): self
    {
        $this->statement->having($having);
        return $this;
    }

    /**
     * @see SqlSelect::orderBy()
     * @return $this
     */
    public function orderBy($orderBy, $sortdir_or_replace = null): self
    {
        $this->statement->orderBy($orderBy, $sortdir_or_replace);
        return $this;
    }

    /**
     * @see SqlSelect::limit()
     * @return $this
     */
    public function limit(int $limit): self
    {
        $this->statement->limit($limit);
        return $this;
    }

    /**
     * @see SqlSelect::offset()
     * @return $this
     */
    public function offset(int $offset): self
    {
        $this->statement->offset($offset);
        return $this;
    }

    /**
     * @see SqlSelect::union()
     * @return $this
     */
    public function union(SqlSelect $select, bool $all = false): self
    {
        $this->statement->union($select, $all);
        return $this;
    }

    /**
     * @see SqlSelect::intersect()
     * @return $this
     */
    public function intersect(SqlSelect $select): self
    {
        $this->statement->intersect($select);
        return $this;
    }

    /**
     * Index the result by the given identifier
     *
     * @var string $identifier
     * @return $this
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
     * @return array<string|int, mixed>[]
     * @throws RuntimeException
     */
    public function fetchAll(
        int $fetch_mode = PDO::FETCH_ASSOC,
        $fetch_argument = null,
        array $ctor_args = []
    ): array {
        if (null === $stmt = $this->execute()) {
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
            case PDO::FETCH_LAZY:
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

        throw InvalidArgumentException(
            "Invalid fetch_mode combination pipe `{$fetch_mode}` for indexed-rowset!"
        );
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
        $this->statement->limit(1);
        if (null === $stmt = $this->execute()) {
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
                    throw InvalidArgumentException(sprintf(
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
                    throw InvalidArgumentException(sprintf(
                        "\$class_or_object MUST be an object when fetch_mode"
                        . " = `PDO::FETCH_INTO`, `%s` provided!",
                        gettype($class_or_object)
                    ));
                }
                $obj = $class_or_object;
                $obj = $stmt->fetch($fetch_mode);
                return is_object($obj) ? $obj : null;
        }

        throw InvalidArgumentException(
            "Invalid fetch_mode combination pipe `{$fetch_mode}`!"
        );
    }

    /**
     * Fetch a column of the first row, if any, after executing the composed sql statement
     *
     * @return null|string
     */
    public function fetchScalar(string $identifier): ?string
    {
        $this->statement->limit(1);
        if (null === $stmt = $this->execute()) {
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
        $this->statement->limit(1);
        if (null === $stmt = $this->execute()) {
            return null;
        }

        $value = $stmt->fetchColumn($column_number);
        $stmt->closeCursor();

        return $value;
    }
}
