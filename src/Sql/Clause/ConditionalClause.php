<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Clause;

use P3\Db\Sql;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Element;
use P3\Db\Sql\Predicate\Set as PredicateSet;

use function ltrim;
use function preg_replace;
use function strrchr;
use function strtoupper;

/**
 * This class abstracts the SQL conditional clauses WHERE, HAVING and ON
 */
abstract class ConditionalClause extends Element
{
    /**
     * @var string The SQL-clause name: WHERE|HAVING|ON condition clauses
     */
    protected static $name;

    /**
     * @var string WHERE|HAVING|ON Resolved name cache
     */
    protected $__name;

    /**
     * @var PredicateSet
     */
    protected $conditions;

    /**
     * @param string $combined_by One of `AND`, `OR`, `&&`, `||`
     * @param null|Predicate[]|self|Predicate|array|string $predicates
     */
    public function __construct(string $combined_by = null, $predicates = null)
    {
        $this->conditions = new PredicateSet($combined_by, $predicates);
    }

    public function getSQL(Driver $driver = null): string
    {
        $predicates_sql = $this->conditions->getSQL($driver ?? Driver::ansi());
        if (Sql::isEmptySQL($predicates_sql)) {
            return '';
        }
        $this->importParams($this->conditions);

        return "{$this->getName()} {$predicates_sql}";
    }

    /**
     * Return the SQL name for the clause (uppercase class-basename)
     * @return string
     */
    protected function getName(): string
    {
        // use the statically defined name if set
        if (!empty(static::$name)) {
            return static::$name;
        }

        // use the cached name value if set
        if (!empty($this->__name)) {
            return $this->__name;
        }

        $class_basename = ltrim(strrchr(static::class, '\\'), '\\');
        $name = preg_replace('/[a-z][A-Z]/', '$1 $2', $class_basename);
        $this->__name = strtoupper($name);

        return $this->__name;
    }
}
