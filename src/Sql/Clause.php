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
     *
     * @var string WHERE|HAVING|ON
     */
    protected static $name;

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

        $fqcn = static::class;
        static::$name = strtoupper(substr($fqcn, strrpos($fqcn, '\\') + 1));

        return static::$name;
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
