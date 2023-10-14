<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\Db\Sql;

use pine3ree\Db\Exception\InvalidArgumentException;
use pine3ree\Db\Exception\RuntimeException;
use pine3ree\Db\Sql\Alias;
use pine3ree\Db\Sql\DriverInterface;
use pine3ree\Db\Sql\ElementInterface;
use pine3ree\Db\Sql\Identifier;
use pine3ree\Db\Sql\Literal;
use pine3ree\Db\Sql\Params;
use ReflectionClass;

use function get_class;
use function gettype;
use function is_object;
use function is_scalar;
use function is_string;
use function sprintf;
use function trim;

/**
 * This abstract class represents a generic SQL element and is the ancestor
 * of all the other sql-related classes.
 *
 * @property-read Params|null $params The parameters collector, if any
 * @property-read ElementInterface|null $parent The parent element, if any
*/
abstract class Element implements ElementInterface
{
    /**
     * The parent element, if any
     */
    protected ?ElementInterface $parent = null;

    /**
     * The rendered SQL statement string with optional parameter markers
     */
    protected ?string $sql = null;

    /**
     * The element's parameters collector, if any
     */
    protected ?Params $params = null;

    /**
     * Flag updated by getSQL(...) calls and telling if the parameter accumulator
     * has changed since last call
     */
    protected bool $params_changed = false;

    /**
     * The last driver argument used in this element getSQL() call
     */
    protected ?DriverInterface $driver = null;

    /**
     * Flag updated by getSQL(...) calls and telling if the driver used to
     * generate the sql string has changed since last call
     */
    protected bool $driver_changed = false;

    /**
     * A cache for element classes short name
     *
     * @var array|string[]|array<string, string>
     */
    protected static array $shortNames = [];

    public function hasParams(): bool
    {
        return isset($this->params) && !$this->params->isEmpty();
    }

    public function getParams(): ?Params
    {
        return $this->params;
    }

    public function getParent(): ?ElementInterface
    {
        return $this->parent;
    }

    /**
     * Set the parent element.
     *
     * Raises exception if parent is already set.
     *
     * @internal
     *
     * @return void
     * @throws RuntimeException
     */
    public function setParent(ElementInterface $parent): void
    {
        if ($this->parent instanceof ElementInterface && $this->parent !== $parent) {
            throw new RuntimeException(sprintf(
                "The parent of this `%s` sql-element is already set!",
                static::class
            ));
        }

        $this->parent = $parent;
    }

    /**
     * Return true if element has a parent different from the given element
     *
     * @param ElementInterface $parent
     */
    protected function parentIsNot(ElementInterface $parent): bool
    {
        return isset($this->parent) && $this->parent !== $parent;
    }

    /**
     * Move to the closest ancestor element of given class, if any, or return NULL
     *
     * @param string $fqcn The fully-qualified-class-name of the ancestor we are looking for
     * @param bool $strict Flag for strict class-name matching, or return first innstanceof the class
     * @return ElementInterface|null Provides fluent interface
     */
    protected function closest(string $fqcn, bool $strict = false): ?ElementInterface
    {
        $closest = $this->parent;
        while ($closest) {
            if ($closest instanceof $fqcn) {
                if (!$strict || $fqcn === get_class($closest)) {
                    return $closest;
                }
            }
            $closest = $closest->parent;
        }

        return $closest;
    }

    /**
     * Get the class basename
     */
    protected function getShortName(): string
    {
        return
            self::$shortNames[static::class] ?? (
            self::$shortNames[static::class] = (new ReflectionClass($this))->getShortName()
        );
    }

    /**
     * Create a SQL representation (either the actual sql-string or a sql-marker)
     * for a given value
     *
     * @param mixed $value
     * @param int|null $type Optional PDO::PARAM_* constant
     * @param string|null $name Optional parameter name seed for pdo marker generation
     */
    protected function getValueSQL(Params $params, $value, ?int $type = null, ?string $name = null): string
    {
        return $value instanceof Literal
            ? $value->getSQL()
            : $params->create($value, $type, $name);
    }

    /**
     * Quote a generic identifier (column|alias|literal) used in predicates, group-by,
     * order-by clauses according to its type
     *
     * @param mixed $identifier
     * @param DriverInterface $driver A SQL-driver
     * @throws InvalidArgumentException
     */
    protected function getIdentifierSQL($identifier, DriverInterface $driver): string
    {
        // The identifier is considered a db table column, quote accordingly
        if (is_string($identifier)) {
            return $driver->quoteIdentifier($identifier);
        }

        // The indentifier is a SQL-identifier or a SQL-alias, return quoted expressions
        if ($identifier instanceof Identifier || $identifier instanceof Alias) {
            return $identifier->getSQL($driver);
        }

        // The identifier is a generic SQL-literal, so no quoting
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
     *
     * @throws InvalidArgumentException
     */
    protected static function assertValidIdentifier(&$identifier, string $type = ''): void
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

    /**
     * Make sure the value can be used as a SQL value.
     *
     * Only scalar, null and sql-literal values are supported.
     *
     * @param mixed $value
     * @param string $type A string identifier for the exception message
     *
     * @throws InvalidArgumentException
     */
    protected static function assertValidValue($value, string $type = ''): void
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
     * Check that the given SQL is a non-empty string
     *
     * @param string $sql The SQL-string to check
     */
    protected static function isEmptySQL(string &$sql): bool
    {
        return '' === ($sql = trim($sql));
    }

    /**
     * Remove the cached SQL string and the collected parameters from this element
     * and all its parent elements
     */
    protected function clearSQL(): void
    {
        $this->sql = null;
        $this->params = null;
        $this->driver = null;
        if ($this->parent instanceof self) {
            $this->parent->clearSQL();
        }
    }

    protected function hasValidSqlCache(?Driver $driver, ?Params $params): bool
    {
        return (isset($this->sql)
            && isset($this->params)
            && $this->driver === $driver
            && $params === null
        );
    }

    /**
     * Detach the clone from the orginal element's parent, clear the internal
     * sql cache, if any, and emove any previously collected params.
     */
    public function __clone()
    {
        $this->parent = null;
        $this->sql = null;
        $this->params = null;
        $this->driver = null;
    }

    /**
     * Return a property value
     *
     * @param string $name
     * @return mixed
     * @throws RuntimeException
     */
    public function __get(string $name)
    {
        if ('params' === $name) {
            return $this->params;
        };

        if ('parent' === $name) {
            return $this->parent;
        };

        throw new RuntimeException(sprintf(
            "Undefined property `%s` for sql-element of class `%s`!",
            $name,
            static::class
        ));
    }

    public function __isset(string $name): bool
    {
        return isset($this->{$name});
    }
}
