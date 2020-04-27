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
 * Class Like
 */
class Like extends Predicate
{
    protected $identifier;
    protected $value;
    protected $not = false;

    public function __construct(string $identifier, $value)
    {
        $this->identifier = $identifier;
        $this->value = $value;
    }

    public function getSQL(): string
    {
        $identifier = $this->quoteIdentifier($this->identifier);
        $operator   = ($this->not ? "NOT " : "") . "LIKE";

        $param = $this->value instanceof Literal
            ? (string)$this->value
            : $this->createNamedParam($this->value);

        return "{$identifier} {$operator} {$param}";
    }
}
