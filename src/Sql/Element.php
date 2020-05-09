<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use PDO;
use P3\Db\Sql\Driver;

use function is_bool;
use function is_int;
use function is_null;
use function is_string;
use function trim;

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


    public function hasParams(): bool
    {
        return !empty($this->params);
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function getParamsTypes(bool $show_pdo_const_names = false): array
    {
        if ($show_pdo_const_names && !empty($this->params_types)) {
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
     * Import parameters and types from inner elements
     *
     * @param self $expr
     * @internal
     */
    public function importParams(self $expr): void
    {
        if (empty($expr->params)) {
            return;
        }

        foreach ($expr->params as $key => $value) {
            $this->params[$key] = $value;
            $this->params_types[$key] = $expr->params_types[$key] ?? PDO::PARAM_STR;
        }
    }

    abstract public function getSQL(Driver $driver = null): string;

    /**
     * Create a statement string marker for a given value
     *
     * @param mixed $value The parameter value
     * @param int $type The optional forced parameter type
     *
     * @return string
     */
    public function createNamedParam($value, int $type = null): string
    {
        $marker = ":" . self::$index;
        $this->setParam($marker, $value, $type);
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
        $this->setParam(self::$index, $value, $type);
        self::incrementIndex();

        return '?';
    }

    private static function incrementIndex()
    {
        self::$index = self::$index < self::MAX_INDEX ? (self::$index + 1) : 1;
    }

    /**
     * Add a parameter and its type to the internal list
     *
     * @param int|string $key
     * @param mixed $value
     * @param int $type
     */
    protected function setParam($key, $value, int $type = null)
    {
        $this->params[$key] = $value;

        if (!isset($param_type)) {
            if (is_null($value)) {
                $param_type = PDO::PARAM_NULL;
            } elseif (is_int($value) || is_bool($value)) {
                $param_type = PDO::PARAM_INT;
            } else {
                $param_type = PDO::PARAM_STR;
            }
        }

        $this->params_types[$key] = $param_type;
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
