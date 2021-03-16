<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use PDO;

use function is_bool;
use function is_int;
use function is_null;

/**
 * An abstraction of a SQL-statement's parameters collector
 */
class Params
{
    /**
     * The parameters indexing mode, either named (default) or positional
     *
     * @var int
     */
    private $mode;

    /**
     * @var array<string, mixed> A collection of marker-indexed sql-statement parameters
     */
    private $values = [];

    /**
     * A collection of marker-indexed types for sql-statement parameters
     * Types are expressed using PDO::PARAM_* constants
     *
     * @var array<string, int>
     */
    private $types = [];

    /**
     * The parameter counter
     *
     * @var int
     */
    private $count = 0;

    /**
     * An array of named-parameter indexes categorized by hinted names
     *
     * @var array<string, int>
     */
    private $index = [];

    public const MODE_NAMED = 1;
    public const MODE_POSITIONAL = 2;

    private const DEFAULT_NAME = 'param';

    public function __construct(int $mode = self::MODE_NAMED)
    {
        $this->mode = $mode === self::MODE_POSITIONAL ? $mode : self::MODE_NAMED;
    }

    /**
     * Check if there are any parameters after compiling the sql string
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->count === 0;
    }

    /**
     * Return the parameters values created after compiling the sql string, indexed
     * by their sql markers
     *
     * @return array<string|int, mixed>
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * Return the parameters values types indexed by their sql markers or the 1-indexed
     * position depending on this element's collecting mode
     *
     * @return array<string|int, int>
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    /**
     * Add a parameter to the collection creating and returning either a unique
     * SQL-string marker if in named-parameters mode or the common '?' positional
     * marker.
     *
     * @param mixed $value The parameter value
     * @param int|null $type The optional pre-determined parameter's value type
     *      chosen among the PDO::PARAM_* constants
     * @param string|null $name An optional seed/hint for the parameter name
     *
     * @return string
     */
    public function create($value, int $type = null, string $name = null): string
    {
        $this->count += 1;

        if ($this->mode === self::MODE_NAMED) {
            $name = $name ?: self::DEFAULT_NAME;
            $marker = ":{$name}{$this->getNextIndex($name)}";
            $this->setParam($marker, $value, $type);
            return $marker;
        }

        // PDO positional parameters are 1-indexed so we use the parameter counter
        $this->setParam($this->count, $value, $type);
        return '?';
    }

    private function getNextIndex(string $name): int
    {
        if (!isset($this->index[$name])) {
            return $this->index[$name] = 1;
        }

        return $this->index[$name] += 1;
    }

    /**
     * Add a parameter value and its type to the internal list
     *
     * @param string|int $index
     * @param mixed $value
     * @param int $type
     */
    private function setParam($index, $value, int $type = null, string $name = null)
    {
        $this->values[$index] = $value;

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

        $this->types[$index] = $type;
    }
}
