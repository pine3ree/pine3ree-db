<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use P3\Db\Exception\InvalidArgumentException;
use P3\Db\Sql\Driver;
use P3\Db\Sql\DriverInterface;
use P3\Db\Sql\Predicate;
use P3\Db\Exception\RuntimeException;

use function preg_quote;
use function preg_replace;
use function strpos;
use function trim;

/**
 * This class represents a sql expression predicate with parameter markers
 *
 * @property-read string $expression The expression string itself
 * @property-read string $substitutions The placeholder substitutions
 */
class Expression extends Predicate
{
    /**
     * @var string The "{name}"-parametrized SQL-expression
     */
    private $expression;

    /**
     * @var array
     */
    private $substitutions = [];

    /**
     * @param string $expression The SQL expression with optional {name}-placeholders
     * @param array $substitutions A list of substitution parameters for the expression
     *      indexed by placeholder
     * @throws InvalidArgumentException
     */
    public function __construct(string $expression, array $substitutions = [])
    {
        $expression = trim($expression);
        if ('' === $expression) {
            throw new InvalidArgumentException(
                "A SQL-expression cannot be empty!"
            );
        }
        $this->expression = $expression;
        foreach ($substitutions as $name => $value) {
            if (false === strpos($expression, "{{$name}}")) {
                throw new InvalidArgumentException(
                    "Placeholder `{{$name}}` not found in the sql-expression!"
                );
            }
            $this->substitutions[$name] = $value;
        }
    }

    public function getSQL(DriverInterface $driver = null): string
    {
        if (isset($this->sql)) {
            return $this->sql;
        }

        if (empty($this->substitutions)) {
            return $this->sql = $this->expression;
        }

        $this->resetParams();

        $driver = $driver ?? Driver::ansi();

        // reset any previous parameters
        $this->params = $this->paramsTypes = [];

        // replace the `{name}`-placeholders with `:name`-markers
        $sql = $this->expression;
        foreach ($this->substitutions as $name => $value) {
            $search = "{{$name}}";
            while (strpos($sql, $search) !== false) {
                $sql = preg_replace(
                    '/' . preg_quote($search) . '/',
                    $this->getValueSQL($value, null, 'expr'),
                    $sql,
                    1
                );
            }
        }

        return $this->sql = $sql;
    }

    public function __get(string $name)
    {
        if ('expression' === $name) {
            return $this->expression;
        };

        if ('substitutions' === $name) {
            return $this->substitutions;
        };

        throw new RuntimeException(
            "Undefined property {$name}!"
        );
    }
}
