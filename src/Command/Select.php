<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Command;

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
    public function orderBy($orderBy, $sortOrReplace = null): self
    {
        $this->statement->orderBy($orderBy, $sortOrReplace);
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
    public function union(SqlSelect $select): self
    {
        $this->statement->union($select);
        return $this;
    }

    /**
     * Index the result by the given identifier
     *
     * @var string $indexBy
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
     * @see \PDOCommand::fetchAll()
     *
     * @return array<string|int, mixed>[]
     */
    public function fetchAll(int $fetch_mode = PDO::FETCH_ASSOC): array
    {
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

        if ($fetch_mode === PDO::FETCH_CLASS
            || $fetch_mode === PDO::FETCH_OBJ
        ) {
            foreach ($rows as $i => $obj) {
                $index = $obj->{$indexBy};
                if (!isset($index)) {
                    throw new RuntimeException(
                        "The indexBy identifier `{$indexBy}` is not a valid property"
                        . " in the result object with index={$i}!"
                    );
                }
                $indexed[$index] = $obj;
            }

            return $indexed;
        }

        foreach ($rows as $i => $row) {
            $index = $row[$indexBy];
            if (!isset($index)) {
                throw new RuntimeException(
                    "The indexBy identifier `{$indexBy}` is not a valid key in"
                    . " the result row with index={$i}!"
                );
            }
            $indexed[$index] = $row;
        }

        return $indexed;
    }

    /**
     * Fetch the first row, if any, after executing the composed sql statement
     *
     * @return array|null
     */
    public function fetchOne(int $fetch_mode = PDO::FETCH_ASSOC): ?array
    {
        $this->statement->limit(1);

        if (null === $stmt = $this->execute()) {
            return null;
        }

        $args = func_get_args();

        $row = empty($args)
            ? $stmt->fetch($fetch_mode)
            : $stmt->fetch(...$args);

        $stmt->closeCursor();

        return is_array($row) ? $row : null;
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
     * Fetch the first column of the first row, if any, after executing the composed sql statement
     *
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
