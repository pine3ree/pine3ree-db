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
     * @var string|null The SQL-clause base name: WHERE|HAVING|ON condition clauses
     */
    protected static $name;

    /**
     * Return the SQL name for the clause (uppercase class-basename) including
     * optional modifiers
     *
     * @staticvar string $name WHERE|HAVING|ON|JOIN Resolved name(+modifiers) cached value
     * @return string
     */
    protected function getName(): string
    {
        static $name = null;

        // use the statically defined name if set
        if (isset(static::$name)) {
            return static::$name;
        }

        // use the cached name value if set
        if (isset($name)) {
            return $name;
        }

        $name = preg_replace('/([a-z])([A-Z])/', '$1 $2', $this->getShortName());
        $name = strtoupper($name ?? $this->getShortName());

        return $name;
    }

    /**
     * @return mixed
     */
    public function __get(string $name)
    {
        if ('name' === $name) {
            return static::$name ?? $this->getName();
        }

        return parent::__get($name);
    }
}
