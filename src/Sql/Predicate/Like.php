<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use InvalidArgumentException;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Predicate;

use function get_class;
use function gettype;
use function is_object;

/**
 * This class represents a sql LIKE condition
 */
class Like extends Predicate
{
    protected $identifier;
    protected $value;
    protected $not = false;

    /**
     * @param string|Literal $identifier
     * @param string|Literal $values
     */
    public function __construct($identifier, $value)
    {
        self::assertValidIdentifier($identifier);
        self::assertValidValue($value);

        $this->identifier = $identifier;
        $this->value = $value;
    }

    protected static function assertValidValue($value)
    {
        if (!is_string($value) && ! $value instanceof Literal) {
            throw new InvalidArgumentException(sprintf(
                "A LIKE value must be either a string or an Sql Literal expression instance, `%s` provided!",
                is_object($value) ? get_class($value) : gettype($value)
            ));
        }
    }

    public function getSQL(): string
    {
        if (isset($this->sql)) {
            return $this->sql;
        }

        $identifier = $this->identifier instanceof Literal
            ? $this->identifier->getSQL()
            : $this->quoteIdentifier($this->identifier);

        $operator = ($this->not ? "NOT " : "") . "LIKE";

        $param = $this->value instanceof Literal
            ? $this->value->getSQL()
            : $this->createNamedParam($this->value);

        return $this->sql = "{$identifier} {$operator} {$param}";
    }
}
