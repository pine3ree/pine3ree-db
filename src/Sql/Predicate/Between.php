<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use P3\Db\Sql\Literal;
use P3\Db\Sql\Predicate;

/**
 * Class Between
 */
class Between extends Predicate
{
    protected $identifier;
    protected $min_value;
    protected $max_value;
    protected $not = false;

    public function __construct(string $identifier, $min_value, $max_value)
    {
        $this->identifier = $identifier;
        $this->min_value  = $min_value;
        $this->max_value  = $max_value;
    }

    public function getSQL(): string
    {
        $identifier = $this->quoteIdentifier($this->identifier);
        $operator   = ($this->not ? "NOT " : "") . "BETWEEN";

        $min = $this->min_value instanceof Literal
            ? (string)$this->min_value
            : $this->createNamedParam($this->min_value);

        $max = $this->max_value instanceof Literal
            ? (string)$this->max_value
            : $this->createNamedParam($this->max_value);

        return "{$identifier} {$operator} {$min} AND {$max}";
    }
}
