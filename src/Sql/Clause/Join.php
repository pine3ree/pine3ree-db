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
use P3\Db\Sql\Predicate\Set as PredicateSet;
use P3\Db\Sql\Statement\Traits\TableAwareTrait;

use function trim;

/**
 * Class Join
 */
class Join extends Clause
{
    use TableAwareTrait;
    use ConditionalClauseAwareTrait;

    protected static $name = Sql::JOIN;

    /** @var string */
    private $type;

    /** @var string */
    private $table;

    /** @var string|null */
    private $alias;

    /** @var On|Literal|null */
    private $specification;

    /**
     *
     * @param string $type
     * @param string $table
     * @param string $alias
     * @param On|Predicate|PredicateSet|array|string|Literal $specification
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
        return !empty($this->type) ? "{$this->type} JOIN" : "JOIN";
    }
}
