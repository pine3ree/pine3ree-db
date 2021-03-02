<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use InvalidArgumentException;
use P3\Db\Sql\Element;
use P3\Db\Sql\Literal;

use function get_class;
use function gettype;
use function is_object;
use function sprintf;

/**
 * A sql-statement object's goal is to abstract a sql statement collecting parts
 * (abtractions for clauses, predicates, ...) later compiled by a sql-driver into
 * a platform pecific SQL-string
 */
abstract class Statement extends Element
{
    /**
     * @var string[] Cached parts of the final sql statement
     */
    protected $sqls = [];

    /**
     * @var int custom index counter
     */
    protected static $index = 0;

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

    protected static function assertValidValue($value, string $type = '')
    {
        if (is_scalar($value) || null === $value || $value instanceof Literal) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            "A {$type}statement value must be either"
            . " a scalar,"
            . " null"
            . " or a Sql Literal expression instance,"
            . " `%s` provided in class``%s!",
            is_object($value) ? get_class($value) : gettype($value),
            static::class
        ));
    }

    /**
     * Remove any cached SQL string
     */
    public function clearSQL()
    {
        parent::clearSQL();
        $this->sqls = [];
    }
}
