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
     * @param string $quantifier
     * @return $this
     */
    public function quantifier(string $quantifier): self
    {
        $this->statement->quantifier($quantifier);
        return $this;
    }

    /**
     * @param string $quantifier
     * @return $this
     */
    public function distinct(): self
    {
        $this->statement->distinct();
        return $this;
    }

    /**
     * @param array|string $columns
     * @return $this
     */
    public function columns($columns): self
    {
        $this->statement->columns($columns);
        return $this;
    }

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
     * @see SqlSelect::innerJoin()
     * @return $this
     */
    public function innerJoin(string $table, string $alias, $cond): self
    {
        $this->statement->innerJoin($table, $alias, $cond);
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


    public function limit(int $limit): self
    {
        $this->statement->limit($limit);
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->statement->offset($offset);
        return $this;
    }

    public function fetchAll(): array
    {
        $stmt = $this->prepare(true);

        if ($stmt === false || false === $stmt->execute()) {
            return [];
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return is_array($rows) ? $rows : [];
    }


    public function fetchOne(): ?array
    {
        $this->statement->limit(1);

        $stmt = $this->prepare(true);
        if ($stmt === false || false === $stmt->execute()) {
            return [];
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return is_array($row) ? $row : null;
    }
}
