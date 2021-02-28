<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use P3\Db\Sql\Driver;
use PDO;
use ReflectionClass;
use RuntimeException;

use function is_bool;
use function is_int;
use function is_null;

/**
 * This abstract class represents a generic SQL element and is the ancestor
 * of all the other sql-related classes.
 */
abstract class Element
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
    protected static $index = 1;

    /**
     * @const int The maximun numeric index after which the param counter reset to 1
     */
    protected const MAX_INDEX = 999999;

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
     * @internal
     */
    public function importParams(self $element): void
    {
        $params = $element->getParams();
        if (empty($params)) {
            return;
        }

        // The operation fails if the inner element's sql string has not been
        // rendered yet, because it's during rendering that parameters and their
        // sql placeholders are created
        if (!isset($element->sql)) {
            throw new RuntimeException(
                "Cannot import parameters from sql-element without a compiled SQL string!"
            );
        }

        $types = $element->getParamsTypes();
        foreach ($params as $key => $value) {
            $this->addParam($key, $value, $types[$key] ?? null);
        }
    }

    /**
     * Build and return the parametrized SQL-string
     *
     * This method must call each inner element getSQL() method and then import
     * its parameters
     */
    abstract public function getSQL(Driver $driver = null): string;

    /**
     * Create a SQL-string marker for the given value
     *
     * @param mixed $value The parameter value
     * @param int|null $type The optional forced parameter type
     * @param string|null $name The optional original parameter name
     *
     * @return string
     */
    public function createParam($value, int $type = null, string $name = null): string
    {
        return $this->createNamedParam($value, $type, $name);
        //return $this->createPositionalParam($value, $type);
    }

    /**
     * Create a statement string marker for a given value
     *
     * @param mixed $value The parameter value
     * @param int|null $type The optional forced parameter type
     * @param string|null $name The optional original parameter name
     *
     * @return string
     */
    public function createNamedParam($value, int $type = null, string $name = null): string
    {
        $name = strtolower($name ?? $this->shortName ?? $this->getShortName());
        $marker = ":{$name}" . self::$index;
        $this->addParam($marker, $value, $type);
        self::incrementIndex();

        return $marker;
    }

    /**
     * Create a statement string marker for a given value
     *
     * @param mixed $value The parameter value
     * @param int $type The optional forced parameter type
     *
     * @return string
     */
    public function createPositionalParam($value, int $type = null): string
    {
        $this->addParam(self::$index, $value, $type);
        self::incrementIndex();

        return '?';
    }

    /**
     * Add a parameter and its type to the internal list
     *
     * @param int|string|null $key
     * @param mixed $value
     * @param int $type
     */
    protected function addParam($key, $value, int $type = null)
    {
        if (null === $key || is_int($key)) {
            $key = count($this->params) + 1;
        }

        $this->params[$key] = $value;

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

        $this->params_types[$key] = $type;
    }

    /**
     * Parameters and types must be reset before computing the element's SQL string
     */
    protected function resetParams()
    {
        $this->params = $this->params_types = [];
    }

    private static function incrementIndex()
    {
        self::$index = self::$index < self::MAX_INDEX ? (self::$index + 1) : 1;
    }

    /**
     * Remove the cached SQL string
     */
    public function clearSQL()
    {
        $this->sql = null;
    }

    public function __clone()
    {
        $this->clearSQL();
    }
}
