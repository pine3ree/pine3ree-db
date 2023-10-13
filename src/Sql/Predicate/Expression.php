<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\Db\Sql\Predicate;

use pine3ree\Db\Exception\InvalidArgumentException;
use pine3ree\Db\Sql\Alias;
use pine3ree\Db\Sql\Driver;
use pine3ree\Db\Sql\DriverInterface;
use pine3ree\Db\Sql\Identifier;
use pine3ree\Db\Sql\Literal;
use pine3ree\Db\Sql\Params;
use pine3ree\Db\Sql\Predicate;

use function get_class;
use function gettype;
use function is_object;
use function is_scalar;
use function sprintf;
use function strlen;
use function strpos;
use function substr_replace;
use function trim;

/**
 * This class represents a sql expression predicate with parameter markers
 *
 * @property-read string $expression The expression string itself
 * @property-read array $substitutions The placeholder substitutions
 */
class Expression extends Predicate
{
    /** The "{name}"-parametrized SQL-expression */
    private string $expression;

    /**
     * @var array|mixed[]|array<string, mixed>
     */
    private array $substitutions = [];

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
            $this->assertValidSubstitution($name, $value);
            $this->substitutions[$name] = $value;
        }
    }

    /**
     * @param string $name
     * @param mixed $value
     *
     * @throws InvalidArgumentException
     */
    protected function assertValidSubstitution(string $name, $value): void
    {
        if (false === strpos($this->expression, "{{$name}}")) {
            throw new InvalidArgumentException(
                "Placeholder `{{$name}}` not found in the sql-expression!"
            );
        }

        if (is_scalar($value)
            || $value === null
            || $value instanceof Literal
            || $value instanceof Identifier
            || $value instanceof Alias
        ) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            "An expression-substitution value must be either"
            . " a scalar,"
            . " null,"
            . " a SQL-literal,"
            . " a SQL-alias or"
            . " a SQL-identifier,"
            . " `%s` provided in class``%s!",
            is_object($value) ? get_class($value) : gettype($value),
            static::class
        ));
    }

    public function getSQL(DriverInterface $driver = null, Params $params = null): string
    {
        if ($this->hasValidSqlCache($driver, $params)) {
            return $this->sql;
        }

        if (empty($this->substitutions)) {
            return $this->sql = $this->expression;
        }

        $this->driver = $driver; // Set last used driver argument
        $this->params = null; // Reset previously collected params, if any

        $driver = $driver ?? Driver::ansi();
        $params = $params ?? ($this->params = new Params());

        // Replace the `{name}`-placeholders with `:name`-markers
        $sql = $this->expression;
        foreach ($this->substitutions as $name => $value) {
            $search = "{{$name}}";
            $length = strlen($search);
            while (false !== $pos = strpos($sql, $search)) {
                $sql = substr_replace(
                    $sql,
                    $this->getSubstitutionValueSQL($driver, $params, $value, null, 'expr'),
                    $pos,
                    $length
                );
            }
        }

        return $this->sql = $sql;
    }

    /**
     * Create a SQL marker for given substitution value
     *
     * @param DriverInterface $driver
     * @param Params $params
     * @param mixed $value
     * @param int $param_type
     * @param string $name
     * @return string
     */
    protected function getSubstitutionValueSQL(
        DriverInterface $driver,
        Params $params,
        $value,
        int $param_type = null,
        string $name = null
    ): string {
        if ($value instanceof Identifier || $value instanceof Alias) {
            return $this->getIdentifierSQL($value, $driver);
        }

        return parent::getValueSQL($params, $value, $param_type, $name);
    }

    /**
     * @return mixed
     */
    public function __get(string $name)
    {
        if ('expression' === $name) {
            return $this->expression;
        };

        if ('substitutions' === $name) {
            return $this->substitutions;
        };

        return parent::__get($name);
    }
}
