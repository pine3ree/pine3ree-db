<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Traits;

use InvalidArgumentException;
use P3\Db\Sql\Driver;

use function str_replace;
use function strpos;
use function trim;

/**
 * Provide the getSQL() method for parametric sql-expression
 */
trait ExpressionTrait
{
    /**
     * @var string The "{name}"-parametrized SQL-expression
     */
    private $expression;

    /**
     * @var array
     */
    private $substitutions;

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

    public function getSQL(Driver $driver = null): string
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
        $this->params = $this->params_types = [];

        // replace the `{name}`-placeholders with `:name`-markers
        $sql = $this->expression;
        foreach ($this->substitutions as $name => $value) {
            $marker = $this->createNamedParam($value);
            $sql = str_replace("{{$name}}", $marker, $sql);
        }

        return $this->sql = $sql;
    }
}
