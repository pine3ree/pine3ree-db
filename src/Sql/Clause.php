<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use P3\Db\Sql\Element;

use function preg_replace;
use function strtoupper;

/**
 * This class abstracts SQL clauses such as WHERE, HAVING, ON, JOIN
 *
 * @property-read string $name The Clause Name
 */
abstract class Clause extends Element
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
     * Return the SQL name for the clause (uppercase class-basename)
     * @return string
     */
    protected function getName(): string
    {
        // use the statically defined name if set
        if (isset(static::$name)) {
            return static::$name;
        }

        // use the cached name value if set
        if (isset($this->__name)) {
            return $this->__name;
        }

        $name = preg_replace('/[a-z][A-Z]/', '$1 $2', $this->getShortName());
        $this->__name = strtoupper($name);

        return $this->__name;
    }

    public function __get(string $name)
    {
        if ('name' === $name) {
            return static::$name ?? $this->__name ?? $this->getName();
        }
    }
}
