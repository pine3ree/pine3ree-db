<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use P3\Db\Exception\InvalidArgumentException;
use P3\Db\Sql\Alias;
use P3\Db\Sql\DriverInterface;
use P3\Db\Sql\Identifier;
use P3\Db\Sql\Literal;
use P3\Db\Sql\ElementInterface;
use P3\Db\Sql\Params;
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
     * @var string|null The rendered SQL statement string with optional parameter markers
     */
    protected $sql;

    /**
     * @var Params|null The element's parameters collector, if any
     */
    protected $params;

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
     * {@inheritDoc}
     */
    abstract public function getSQL(DriverInterface $driver = null, Params $params = null): string;

    /**
     * Remove the cached SQL string
     */
    protected function clearSQL()
    {
        $this->sql = null;
        $this->params = null;
        if ($this->parent instanceof self) {
            $this->parent->clearSQL();
        }
    }

    public function __clone()
    {
        $this->parent = null;
        $this->sql = null;
        $this->params = null;
    }

    /**
     * {@inheritDoc}
     */
    public function hasParams(): bool
    {
        return isset($this->params) ? !$this->params->isEmpty() : false;
    }

    /**
     * {@inheritDoc}
     */
    public function getParams(): ?Params
    {
        return $this->params;
    }

    /**
     * {@inheritDoc}
     */
    public function getParamsValues(): array
    {
        return isset($this->params) ? $this->params->getValues() : [];
    }

    /**
     * {@inheritDoc}
     */
    public function getParamsTypes(): array
    {
        return isset($this->params) ? $this->params->getTypes() : [];
    }

    /**
     * {@inheritDoc}
     */
    public function hasParent(): bool
    {
        return $this->parent instanceof ElementInterface;
    }

    /**
     * {@inheritDoc}
     */
    public function getParent(): ?ElementInterface
    {
        return $this->parent;
    }

    /**
     * {@inheritDoc}
     */
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
     * Parameters and types must be reset before computing the element's SQL string
     */
    protected function resetParams()
    {
        $this->params = null;
    }

    /**
     * Create a SQL representation (either actual string or marker) for a given value
     *
     * @param mixed $value
     * @param int|null $param_type Optional PDO::PARAM_* constant
     * @param string|null $name Optional parameter name seed for pdo marker generation
     * @return string
     */
    protected function getValueSQL(Params $params, $value, int $param_type = null, string $name = null): string
    {
        return $value instanceof Literal
            ? $value->getSQL()
            : $params->createParam($value, $param_type, $name);
    }

    /**
     * Quote a generic identifier (column|alias|literal) used in predicates, group-by,
     * order-by clauses according to its type
     *
     * @param string|Alias|Identifier|Literal $identifier
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

        throw new RuntimeException(sprintf(
            "Undefined property `%s` for sql-element of class `%s`!",
            $name,
            static::class
        ));
    }
}
