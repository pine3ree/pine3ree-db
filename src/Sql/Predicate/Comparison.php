<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use InvalidApplicationNameException;
use P3\Db\Sql;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Predicate;

/**
 * Class Comparison
 */
class Comparison extends Predicate
{
    protected $identifier;
    protected $operator;
    protected $value;

    private const OPERATORS = [
        Sql::EQ  => Sql::EQ,
        Sql::NEQ => Sql::NEQ,
        Sql::NE  => Sql::NE,
        Sql::LT  => Sql::LT,
        Sql::LTE => Sql::LTE,
        Sql::GTE => Sql::GTE,
        Sql::GT  => Sql::GT,
    ];

    public function __construct(string $identifier, string $operator, $value)
    {
        $this->identifier = $identifier;

        if (!isset(self::OPERATORS[$operator])) {
            throw new InvalidApplicationNameException(
                "Invalid comparison operator `{$identifier}`, must be one of "
                . implode(', ', self::OPERATORS) . "!"
            );
        }

        $this->operator = $operator;
        $this->value = $value;
    }

    public function getSQL(): string
    {
        $identifier = $this->quoteIdentifier($this->identifier);

        $param = $this->value instanceof Literal
            ? (string)$this->value
            : $this->createNamedParam($this->value);

        return "{$identifier} {$this->operator} {$param}";
    }
}
