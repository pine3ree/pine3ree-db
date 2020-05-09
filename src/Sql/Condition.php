<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use P3\Db\Sql\Driver;
use P3\Db\Sql\PredicateSet;

/**
 * This class abstracts the SQL conditional clauses WHERE, HAVING and ON
 */
abstract class Condition extends PredicateSet
{
    /**
     * @var string WHERE|HAVING|ON consition clauses
     */
    protected static $name;

    /**
     * @var string WHERE|HAVING|ON Resolved name cache
     */
    protected $__name;

    public function getSQL(Driver $driver = null): string
    {
        $predicates_sql = parent::getSQL($driver ?? Driver::ansi());

        if (Sql::isEmptySQL($predicates_sql)) {
            return '';
        }

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

        // e.g P3\Db\Sql\Condition\Having => HAVING
        // e.g P3\Db\Sql\Condition\GroupBy => GROUP BY
        $class_basename = ltrim(strrchr(static::class, '\\'), '\\');
        $name = preg_replace('/[a-z][A-Z]/', '$1 $2', $class_basename);

        $this->__name = strtoupper($name);

        return $this->__name;
    }
}
