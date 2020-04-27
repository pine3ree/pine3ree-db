<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use P3\Db\Sql\Predicate;

/**
 * Class Literal
 */
class Literal extends Predicate
{
    /**
     * @pvar string The literal SQL expression
     */
    private $literal;

    public function __construct(string $literal)
    {
        $this->literal = $literal;
    }

    public function getSQL(): string
    {
        return $this->literal ?? '';
    }

    public function __toString(): string
    {
        return $this->literal ?? '';
    }
}
