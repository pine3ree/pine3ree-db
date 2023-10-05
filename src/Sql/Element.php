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
     * The rendered SQL statement string with optional parameter markers
     *
     * @var string|null
     */
    protected $sql;

    /**
     * The element's parameters collector, if any
     *
     * @var Params|null
     */
    protected $params;

    /**
     * The parent element, if any
     *
     * @var ElementInterface|null
     */
    protected $parent;

    /**
     * The last driver argument used in this element getSQL() call
     *
     * @var DriverInterface|null
     */
    protected $driver;

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

    public function hasParent(): bool
    {
        return $this->parent instanceof ElementInterface;
    }

    public function getParent(): ?ElementInterface
    {
        return $this->parent;
    }

    public function setParent(ElementInterface $parent): void
    {
        if ($this->parent instanceof ElementInterface && $this->parent !== $parent) {
            throw new RuntimeException(
                "The parent of this element is already set!"
            );
        }

        $this->parent = $parent;
    }

    /**
     * Return true if element has a parent different from the given element
     *
     * @param ElementInterface $parent
     * @return bool
     */
    protected function parentIsNot(ElementInterface $parent): bool
    {
        return isset($this->parent) && $this->parent !== $parent;
    }

    /**
     * Get the class basename
     *
     * @staticvar string $shortName The cached base-name of this element's class obtained using reflection
     * @return string
     */
    protected function getShortName(): string
    {
        return self::$shortNames[static::class] ?? (
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
     * @return string
     */
    protected function getValueSQL(Params $params, $value, int $type = null, string $name = null): string
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
     * @return string
     * @throws InvalidArgumentException
     */
    protected function getIdentifierSQL($identifier, DriverInterface $driver): string
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

    /**
     * Make sure the value can be used as a SQL value.
     *
     * Only scalar, null and sql-literal values are supported.
     *
     * @param mixed $value
     * @param string $type A string identifier for the exception message
     * @return void
     * @throws InvalidArgumentException
     */
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
     * Check that the given SQL is a non-empty string
     *
     * @param string $sql
     * @return bool
     */
    protected static function isEmptySQL(string &$sql): bool
    {
        return '' === ($sql = trim($sql));
    }

    /**
     * Remove the cached SQL string and the collected parameters from this element
     * and all its parent elements
     *
     * @return void
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
        if ('parent' === $name) {
            return $this->parent;
        };

        if ('params' === $name) {
            return $this->params;
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
