<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use InvalidArgumentException;
use P3\Db\Sql\Alias;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Identifier;
use P3\Db\Sql\Literal;
use P3\Db\Sql\ElementInterface;
use PDO;
use ReflectionClass;
use RuntimeException;

use function debug_backtrace;
use function count;
use function get_class;
use function gettype;
use function is_bool;
use function is_int;
use function is_null;
use function is_object;
use function is_scalar;
use function is_string;
use function is_subclass_of;
use function sprintf;
use function strtolower;
use function trim;

/**
 * This abstract class represents a generic SQL element and is the ancestor
 * of all the other sql-related classes.
 */
abstract class Element implements ElementInterface
{
    /**
     * @var string The rendered SQL statement string with optional parameter markers
     */
    protected $sql;

    /**
     * @var array<int|string, mixed> A collection of indexed substitution parameters
     */
    protected $params = [];

    /**
     * A collection of indexed types for substitution parameters
     * Types are expressed using PDO::PARAM_* constants
     *
     * @var array<int|string, int>
     */
    protected $params_types = [];

    /** @var string */
    protected $shortName;

    /**
     * The parameter index counter
     *
     * @var int
     */
    protected static $index = 0;

    /**
     * The maximum numeric index after which the named-param counter reset to 1
     *
     * @const int
     */
    private const MAX_INDEX = 999999;

    /**
     * Build and return the parametrized SQL-string
     *
     * This method must call each inner element getSQL() method and then import
     * its parameters
     */
    abstract public function getSQL(Driver $driver = null): string;

    /**
     * Remove the cached SQL string
     */
    protected function clearSQL()
    {
        $this->sql = null;
    }

    public function __clone()
    {
        $this->clearSQL();
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function getParamsTypes(bool $return_pdo_const_names = false): array
    {
        if ($return_pdo_const_names
            && !empty($this->params_types)
        ) {
            $types = [];
            foreach ($this->params_types as $key => $type) {
                if ($type === PDO::PARAM_STR) {
                    $pdo_const = 'PDO::PARAM_STR';
                } elseif ($type === PDO::PARAM_INT) {
                    $pdo_const = 'PDO::PARAM_INT';
                } elseif ($type === PDO::PARAM_NULL) {
                    $pdo_const = 'PDO::PARAM_NULL';
                } elseif ($type === PDO::PARAM_LOB) {
                    $pdo_const = 'PDO::PARAM_LOB';
                } else {
                    $pdo_const = 'UNKNOWN';
                }
                $types[$key] = $pdo_const;
            }
            return $types;
        }

        return $this->params_types;
    }

    /**
     * Import parameters and types from inner element.
     *
     * Since parameters and markers are created during sql rendering, we make
     * sure that sql has been computed in the inner element.
     *
     * @param self $element
     * @return void
     */
    protected function importParams(self $element): void
    {
        // The operation fails if the inner element's sql string has not been
        // rendered yet, because it's during rendering that parameters and their
        // sql placeholders are created
        if (!isset($element->sql)) {
            throw new RuntimeException(
                "Cannot import parameters from sql-element without a compiled SQL string!"
            );
        }

        $params = $element->getParams();
        if (empty($params)) {
            return;
        }

        $types = $element->getParamsTypes();
        foreach ($params as $marker => $value) {
            $this->addParam($marker, $value, $types[$marker] ?? null);
        }
    }

    /**
     * Create a SQL-string marker for the given value
     *
     * @param mixed $value The parameter value
     * @param int|null $type The optional forced parameter type
     * @param string|null $name The optional original parameter name
     *
     * @return string
     */
    protected function createParam($value, int $type = null, string $name = null): string
    {
        $name = strtolower($name ?? $this->shortName ?? $this->getShortName());
        $marker = ":{$name}{$this->getNextIndex()}";
        //$marker = ":{$name}" . bin2hex(random_bytes(4));
        $this->addParam($marker, $value, $type);

        return $marker;
    }

    /**
     * Add a parameter and its type to the internal list
     *
     * @param string $marker
     * @param mixed $value
     * @param int $type
     */
    protected function addParam(string $marker, $value, int $type = null)
    {
        $this->params[$marker] = $value;

        if (!isset($type)) {
            if (is_null($value)) {
                $type = PDO::PARAM_NULL;
            } elseif (is_int($value) || is_bool($value)) {
                // use int-type for bool:
                // @see https://bugs.php.net/bug.php?id=38386
                // @see https://bugs.php.net/bug.php?id=49255
                $type = PDO::PARAM_INT;
            } else {
                $type = PDO::PARAM_STR;
            }
        }

        $this->params_types[$marker] = $type;
    }

    /**
     * Parameters and types must be reset before computing the element's SQL string
     */
    protected function resetParams()
    {
        $this->params = $this->params_types = [];
    }

    private function getNextIndex(): int
    {
        if (static::$index === self::MAX_INDEX) {
            return static::$index = 1;
        }

        return static::$index += 1;
    }

    /**
     * Get the class basename
     *
     * @return string
     */
    protected function getShortName(): string
    {
        return $this->shortName ?? (
            $this->shortName = (new ReflectionClass($this))->getShortName()
        );
    }

    /**
     * Create a SQL representation (either actual string or marker) for a given value
     *
     * @param mixed $value
     * @param int|null $param_type Optional PDO::PARAM_* constant
     * @param string|null $name Optional parameter name seed for pdo marker generation
     * @return string
     */
    protected function getValueSQL($value, int $param_type = null, string $name = null): string
    {
        return $value instanceof Literal
            ? $value->getSQL()
            : $this->createParam($value, $param_type, $name);
    }

    /**
     * Quote a generic identifier (column|alias|literal) used in predicates, group-by,
     * order-by clauses according to its type
     *
     * @param string|Alias|Literal $identifier
     * @param Driver $driver A SQL-driver
     * @return string
     * @throws InvalidArgumentException
     */
    protected static function quoteGenericIdentifier($identifier, Driver $driver): string
    {
        // the identifier is considered a db table column, quote accordingly
        if (is_string($identifier)) {
            return $driver->quoteIdentifier($identifier);
        }

        // The indentifier is a SQL-identifier or a SQL-alias, return quoted expressions
        if ($identifier instanceof Identifier || $identifier instanceof Alias) {
            return $identifier->getSQL($driver);
        }

        // the identifier is generic SQL-literal, so no quoting
        if ($identifier instanceof Literal) {
            return $identifier->getSQL();
        }

        throw new InvalidArgumentException(sprintf(
            "Invalid identifier type, must be either a string, a"
            . " SQL-alias or a SQL-literal, '%s' provided in class `%s`!",
            is_object($identifier) ? get_class($identifier) : gettype($identifier),
            static::class
        ));
    }

    /**
     * Check that the provided identifier is valid (a non empty string, a sql-literal,
     * a sql-identifier or a sql-alias)
     *
     * @param mixed $identifier
     * @param string $type
     * @return void
     * @throws InvalidArgumentException
     */
    protected static function assertValidIdentifier(&$identifier, string $type = '')
    {
        if (is_string($identifier)) {
            $identifier = trim($identifier);
            if ('' === $identifier) {
                throw new InvalidArgumentException(
                    "A string identifier cannot be empty!"
                );
            }
            return;
        }

        if (false
            || $identifier instanceof Identifier
            || $identifier instanceof Alias
            || $identifier instanceof Literal
        ) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            "A {$type}identifier must be either"
            . " a string,"
            . " a SQL-identifier,"
            . " a SQL-alias,"
            . " or a SQL-literal,"
            . " '%s' provided in class `%s`!",
            is_object($identifier) ? get_class($identifier) : gettype($identifier),
            static::class
        ));
    }

    protected static function assertValidValue($value, string $type = '')
    {
        if (is_scalar($value) || null === $value || $value instanceof Literal) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            "A {$type}value must be either"
            . " a scalar,"
            . " null"
            . " or a Sql Literal expression instance,"
            . " `%s` provided in class``%s!",
            is_object($value) ? get_class($value) : gettype($value),
            static::class
        ));
    }

    /**
     * Check that the given SQL is a non-emty string
     *
     * @param type $sql
     * @return bool
     */
    protected static function isEmptySQL($sql): bool
    {
        return !is_string($sql) || '' === trim($sql);
    }

    /**
     * Allow createParam() and importParams() to be called from inside a sql-driver
     *
     * @param string $methodName
     * @param array $args
     * @return mixed
     */
    public function __call(string $methodName, $args)
    {
        if ('createParam' === $methodName
            || 'importParams' === $methodName
        ) {
            list($unused, $caller) = debug_backtrace(false, 2);
            $callerClass = $caller['class'] ?? null;
            if (is_subclass_of($callerClass, Driver::class, true)) {
                return $this->{$methodName}(...$args);
            }
        };

        $class = static::class;
        throw new RuntimeException(
            "Call to undefined or internal method {$class}::{$methodName}())!"
        );
    }
}
