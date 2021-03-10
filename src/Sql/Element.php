<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use P3\Db\Exception\InvalidArgumentException;
use P3\Db\Sql\Alias;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Identifier;
use P3\Db\Sql\Literal;
use P3\Db\Sql\ElementInterface;
use PDO;
use ReflectionClass;
use P3\Db\Exception\RuntimeException;

use function get_class;
use function gettype;
use function is_bool;
use function is_int;
use function is_null;
use function is_object;
use function is_scalar;
use function is_string;
use function sprintf;
use function strtolower;
use function trim;

/**
 * This abstract class represents a generic SQL element and is the ancestor
 * of all the other sql-related classes.
 *
 * @property-read ElementInterface|null $parent The parent element, if any
*/
abstract class Element implements ElementInterface
{
    /**
     * @var string The rendered SQL statement string with optional parameter markers
     */
    protected $sql;

    /**
     * @var array<int|string, mixed> A collection of marker-indexed statement parameters
     */
    protected $params = [];

    /**
     * A collection of marker-indexed types for statement parameters
     * Types are expressed using PDO::PARAM_* constants
     *
     * @var array<int|string, int>
     */
    protected $paramsTypes = [];

    /**
     * The parent element, if any
     *
     * @var ElementInterface|null
     */
    protected $parent;

    /**
     * The cached base-name of this element's class derived using reflection
     *
     * @var string
     */
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
    protected const MAX_INDEX = 999999;

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

    /**
     * Check if there are any parameters after compiling the sql string
     *
     * @return bool
     */
    public function hasParams(): bool
    {
        return !empty($this->params);
    }

    /**
     * Return the parameters values created after compiling the sql string, indexed
     * by their sql markers
     *
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Return the parameters values types indexed by their sql markers
     *
     * @param bool $returnPdoConstNames Return pdo constants names instead of their values
     * @return array
     */
    public function getParamsTypes(bool $returnPdoConstNames = false): array
    {
        if ($returnPdoConstNames && !empty($this->paramsTypes)) {
            $types = [];
            foreach ($this->paramsTypes as $marker => $type) {
                if ($type === PDO::PARAM_STR) {
                    $pdoConstName = 'PDO::PARAM_STR';
                } elseif ($type === PDO::PARAM_INT) {
                    $pdoConstName = 'PDO::PARAM_INT';
                } elseif ($type === PDO::PARAM_NULL) {
                    $pdoConstName = 'PDO::PARAM_NULL';
                } elseif ($type === PDO::PARAM_LOB) {
                    $pdoConstName = 'PDO::PARAM_LOB';
                } else {
                    $pdoConstName = 'UNKNOWN';
                }
                $types[$marker] = $pdoConstName;
            }
            return $types;
        }

        return $this->paramsTypes;
    }

    /**
     * {@inheritDocs}
     */
    public function getParent(): ?ElementInterface
    {
        return $this->parent;
    }

    /**
     * {@inheritDocs}
     */
    public function hasParent(): bool
    {
        return $this->parent instanceof ElementInterface;
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

        $this->paramsTypes[$marker] = $type;
    }

    /**
     * Parameters and types must be reset before computing the element's SQL string
     */
    protected function resetParams()
    {
        $this->params = $this->paramsTypes = [];
    }

    private function getNextIndex(): int
    {
        if (static::$index === static::MAX_INDEX) {
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

        if ($identifier instanceof Identifier
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
     * @param string $sql
     * @return bool
     */
    protected static function isEmptySQL(string &$sql): bool
    {
        return '' === ($sql = trim($sql));
    }

    public function __get(string $name)
    {
        if ('parent' === $name) {
            return $this->parent;
        };

        throw new RuntimeException(
            "Undefined property `{$name}`!"
        );
    }
}
