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
     * @var array<string, mixed> A collection of marker-indexed sql-statement parameters
     */
    protected $values = [];

    /**
     * A collection of marker-indexed types for sql-statement parameters
     * Types are expressed using PDO::PARAM_* constants
     *
     * @var array<string, int>
     */
    protected $types = [];

    /**
     * An array of named-parameter indexes categorized by hinted names
     *
     * @var array<string, int>
     */
    protected $index = [];

    /**
     * Check if there are any parameters after compiling the sql string
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->values);
    }

    /**
     * Return the parameters values created after compiling the sql string, indexed
     * by their sql markers
     *
     * @return array<string, mixed>
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * Return the parameters values types indexed by their sql markers
     *
     * @param bool $returnPdoConstNames Return pdo constants names instead of their values
     * @return array<string, int>
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    /**
     * Create a SQL-string marker for the given value
     *
     * @param mixed $value The parameter value
     * @param int|null $type The optional already determined parameter value type
     * @param string|null $name The optional hint for parameter name
     *
     * @return string
     */
    public function createParam($value, int $type = null, string $name = null): string
    {
        $name = $name ?: 'param';
        $marker = ":{$name}{$this->getNextIndex($name)}";

        $this->addParam($marker, $value, $type);

        return $marker;
    }

    private function getNextIndex(string $name): int
    {
        if (!isset($this->index[$name])) {
            return $this->index[$name] = 1;
        }

        return $this->index[$name] += 1;
    }

    /**
     * Add a parameter and its type to the internal list
     *
     * @param string $marker
     * @param mixed $value
     * @param int $type
     */
    private function addParam(string $marker, $value, int $type = null, string $name = null)
    {
        $this->values[$marker] = $value;

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

        $this->types[$marker] = $type;
    }
}
