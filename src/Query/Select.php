<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Query;

use PDO;
use P3\Db\Db;
use P3\Db\Query;
use P3\Db\Sql\Statement\Select as SqlSelect;
use RuntimeException;

/**
 * Class Select
 *
 * @property-read SqlSelect $statement
 */
class Select extends Query
{
    public function __construct(Db $db, $columns = null, $table = null)
    {
        parent::__construct($db, new SqlSelect($columns, $table));
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
    public function from($table, string $alias = null): self
    {
        $this->statement->from($table, $alias);
        return $this;
    }

    /**
     * @see SqlSelect::join()
     * @return $this
     */
    public function join(string $table, string $alias, $cond): self
    {
        $this->statement->join($table, $alias, $cond);
        return $this;
    }

    /**
     * @see SqlSelect::innerJoin()
     * @return $this
     */
    public function innerJoin(string $table, string $alias, $cond): self
    {
        $this->statement->innerJoin($table, $alias, $cond);
        return $this;
    }

    /**
     * @see SqlSelect::leftJoin()
     * @return $this
     */
    public function leftJoin(string $table, string $alias, $cond): self
    {
        $this->statement->leftJoin($table, $alias, $cond);
        return $this;
    }

    /**
     * @see SqlSelect::rightJoin()
     * @return $this
     */
    public function rightJoin(string $table, string $alias, $cond): self
    {
        $this->statement->rightJoin($table, $alias, $cond);
        return $this;
    }

    /**
     * @see self::addJoin()
     */
    public function naturalJoin(string $table, string $alias, $cond = null): self
    {
        $this->statement->naturalJoin($table, $alias, $cond);
        return $this;
    }

    /**
     * @see self::addJoin()
     */
    public function naturalLeftJoin(string $table, string $alias, $cond = null): self
    {
        $this->statement->naturalLeftJoin($table, $alias, $cond);
        return $this;
    }

    /**
     * @see self::addJoin()
     */
    public function naturalRightJoin(string $table, string $alias, $cond = null): self
    {
        $this->statement->naturalRightJoin($table, $alias, $cond);
        return $this;
    }

    /**
     * @see self::addJoin()
     */
    public function crossJoin(string $table, string $alias, $cond = null): self
    {
        $this->statement->crossJoin($table, $alias, $cond);
        return $this;
    }

    /**
     * @see self::addJoin()
     */
    public function straightJoin(string $table, string $alias, $cond = null): self
    {
        return $this->straightJoin($table, $alias, $cond);
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
     * @see SqlSelect::indexBy()
     * @return $this
     */
    public function indexBy(string $indexBy): self
    {
        $this->statement->indexBy($indexBy);
        return $this;
    }

    /**
     * Fetch all the rows resulting by executing the composed sql-statement
     *
     * @see \PDOStatement::fetchAll()
     *
     * @param int $fetch_mode
     * @param type $fetch_argument
     * @param array $ctor_args
     * @return array
     */
    public function fetchAll(): array
    {
        $this->statement->distinct();

        $stmt = $this->prepare(true);
        if ($stmt === false || false === $stmt->execute()) {
            return [];
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (empty($rows) || !is_array($rows)) {
            return [];
        }

        $indexBy = $this->statement->indexedBy();
        if (empty($indexBy)) {
            return $rows;
        }
        if (!isset($rows[0][$indexBy])) {
            throw new RuntimeException(
                "The indexBy identifier `{$indexBy}` is not a valid key in the result rows!"
            );
        }

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row[$indexBy]] = $row;
        }

        return $rows;
    }

    /**
     * Fetch the first row, if any, after executing the composed sql statement
     *
     * @return array|null
     */
    public function fetchOne()
    {
        $this->statement->limit(1);

        $stmt = $this->prepare(true);
        if ($stmt === false || false === $stmt->execute()) {
            return null;
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return is_array($row) ? $row : null;
    }

    /**
     * Fetch a column of first row, if any, after executing the composed sql statement
     *
     * @return null|string
     */
    public function fetchScalar(string $identifier): ?string
    {
        $this->statement->limit(1);

        $stmt = $this->prepare(true);
        if ($stmt === false || false === $stmt->execute()) {
            return null;
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $row[$identifier] ?? null;
    }
}
