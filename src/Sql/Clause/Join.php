<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Clause;

use InvalidArgumentException;
use P3\Db\Sql;
use P3\Db\Sql\Element;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Predicate;
use P3\Db\Sql\Predicate\Set as PredicateSet;
use P3\Db\Sql\Predicate\Set\ConditionAwareTrait ;
use P3\Db\Sql\Predicate\Set\On;
use P3\Db\Sql\Statement\Traits\TableAwareTrait;

use function ltrim;
use function preg_replace;
use function strrchr;
use function strtoupper;

/**
 * Class Join
 */
class Join extends Element
{
    use TableAwareTrait;
    use ConditionAwareTrait;

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
                $this->setCondition('specification', On::class, $specification);
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

        if (empty($this->specification)) {
            $this->sql = trim("{$this->type} JOIN {$table}");
            return $this->sql;
        }

        if ($this->specification instanceof Literal) {
            $specification = $this->on->getSQL();
        } elseif ($this->specification instanceof On) {
           $specification = $this->getConditionSQL('on', $driver);
           if (!Sql::isEmptySQL($specification)) {
               $this->importParams($this->specification);
           }
        }

        $this->sql = trim("{$this->type} JOIN {$table} {$specification}");
        return $this->sql;
    }
}
