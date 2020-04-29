<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use P3\Db\Sql\PredicateSet;

/**
 * Class Clause
 */
abstract class Clause extends PredicateSet
{
    /**
     * @var string WHERE|HAVING|ON
     */
    protected static $name;

    /**
     * @var string WHERE|HAVING|ON Resolved name cache
     */
    protected $__name;

    public function getSQL(bool $stripParentheses = false): string
    {
        $predicates_sql = parent::getSQL();

        if ($this->isEmptySQL($predicates_sql)) {
            return '';
        }

        if ($stripParentheses) {
            $predicates_sql = $this->stripParentheses($predicates_sql);
        }

        return "{$this->getName()} {$predicates_sql}";
    }

    /**
     * Return the SQL name for the clause (uppercase class-basename)
     * @return string
     */
    protected function getName(): string
    {
        if (!empty(static::$name)) {
            return static::$name;
        }

        // runtime variable
        if (!empty($this->__name)) {
            return $this->__name;
        }

        // e.g P3\Db\Sql\Clause\Having => HAVING
        // e.g P3\Db\Sql\Clause\GroupBy => GROUP BY
        $fqcn = static::class;
        $class_basename = substr($fqcn, strrpos($fqcn, '\\') + 1);
        $name = preg_replace('/[a-z][A-Z]/', '$1 $2', $class_basename);

        $this->__name = strtoupper($name);

        return $this->__name;
    }

    /**
     * Strip any surrounding matching pair of parentheses
     *
     * @param string $sql
     * @return bool
     */
    protected function stripParentheses(string $sql): string
    {
        if ('(' === substr($sql, 0, 1) && substr($sql, -1) === ')') {
            return mb_substr($sql, 1, -1);
        }

        return $sql;
    }
}
