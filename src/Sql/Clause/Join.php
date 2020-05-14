<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Clause;

use P3\Db\Sql;
use P3\Db\Sql\Clause;
use P3\Db\Sql\Clause\ConditionalClauseAwareTrait ;
use P3\Db\Sql\Clause\On;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Predicate;
use P3\Db\Sql\Statement\Traits\TableAwareTrait;

use function trim;

/**
 * Join represents a SQL-JOIN clause
 *
 * @property-read string $name The join sql-name ("join-type JOIN")
 * @property-read string $type The join type
 * @property-read string $table The joined-table
 * @property-read string|null $alias The joined-table alias, if any
 * @property-read On|Literal|null $on The ON-specification, if any
 * @property-read On|null $on The ON-clause (current or new instance) if the specification
 *      is not already set to a Literal
 */
class Join extends Clause
{
    use TableAwareTrait;
    use ConditionalClauseAwareTrait;

    protected static $name = Sql::JOIN;

    /** @var string */
    private $type;

    /** @var string|null */
    private $alias;

    /** @var On|Literal|null */
    private $specification;

    /**
     * @param string $type The join type
     * @param string $table The joined table name
     * @param string|null $alias The joined table alias, if any
     * @param On|Predicate|Predicate\Set|array|string|Literal $specification
     */
    public function __construct(string $type, string $table, string $alias = null, $specification = null)
    {
        Sql::assertValidJoin($type);

        $this->type = $type;
        $this->setTable($table);
        if (isset($alias)) {
            $this->alias = $alias;
        }

        if (!empty($specification)) {
            if ($specification instanceof Literal) {
                $this->specification = $specification;
            } else {
                $this->setConditionalClause('specification', On::class, $specification);
            }
        }
    }

    public function getSQL(Driver $driver = null): string
    {
        if (isset($this->sql)) {
            return $this->sql;
        }

        $driver = $driver ?? Driver::ansi();

        $table = $driver->quoteIdentifier($this->table);
        if (!empty($this->alias)) {
            $table .= " " .  $driver->quoteAlias($this->alias);
        }

        $join = $this->getName();

        if (empty($this->specification)) {
            $this->sql = "{$join} {$table}";
            return $this->sql;
        }

        if ($this->specification instanceof Literal) {
            $specification = $this->on->getSQL();
        } elseif ($this->specification instanceof On) {
            $specification = $this->getConditionalClauseSQL('specification', $driver);
            if (!Sql::isEmptySQL($specification)) {
               $this->importParams($this->specification);
            }
        }

        $this->sql = trim("{$join} {$table} {$specification}");
        return $this->sql;
    }

    protected function getName(): string
    {
        if (isset($this->__name)) {
            return $this->__name;
        }

        $this->__name = !empty($this->type) ? "{$this->type} JOIN" : "JOIN";
        return $this->__name;
    }

    public function __get(string $name)
    {
        if ('name' === $name) {
            return $this->__name ?? $this->getName();
        }
        if ('type' === $name) {
            return $this->type;
        }
        if ('table' === $name) {
            return $this->table;
        }
        if ('alias' === $name) {
            return $this->alias;
        }
        if ('specification' === $name) {
            return $this->specification;
        }
        if ('on' === $name) {
            if ($this->specification === null) {
                return $this->specification = new On();
            }
            if ($this->specification instanceof On) {
                return $this->specification;
            }
            return null;
        }
    }
}
