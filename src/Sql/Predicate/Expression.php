<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use P3\Db\Sql\Predicate;

/**
 * Class Expression
 */
class Expression extends Predicate
{
    /**
     * @pvar string The literal SQL expression
     */
    private $expression;

    public function __construct(string $expression, array $params = [])
    {
        $this->expression = $expression;
        $this->params = $params;
    }

    public function getSQL(): string
    {
        return $this->expression ?? '';
    }

    public function __toString(): string
    {
        return $this->expression ?? '';
    }
}
