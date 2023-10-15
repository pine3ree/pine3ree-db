<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\Db\Sql;

use pine3ree\Db\Sql\Element;

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
     * The clause SQL name, e.g. WHERE|HAVING|ON|JOIN,... clauses
     */
    protected static string $name;

    /**
     * @var array|string[]|array<string, string> A cache of computed sql clause names
     */
    private static array $names;

    /**
     * Return the SQL name for the clause (uppercase class-basename) including
     * optional modifiers
     *
     * @staticvar string $name WHERE|HAVING|ON|JOIN Resolved name(+modifiers) cached value
     * @return string
     */
    protected function getName(): string
    {
        // Use the statically defined name if set
        if (isset(static::$name)) {
            return static::$name;
        }

        $name = self::$names[static::class] ?? null;
        if (isset($name)) {
            return $name;
        }

        $name = preg_replace('/([a-z])([A-Z])/', '$1 $2', $this->getShortName());
        $name = strtoupper($name ?? $this->getShortName());

        self::$names[static::class] = $name;

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
