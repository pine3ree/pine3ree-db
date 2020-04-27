<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use P3\Db\Sql;

/**
 * Class Expression
 */
class Expression
{
    /**
     * @pvar string The literal SQL expression
     */
    private $expression;

    /**
     * @var array
     */
    private $params;

    public function __construct(string $expression, array $params = [])
    {
        $this->expression = $expression;
        $this->params = $params;
    }

    public function getParams(): array
    {
        return $this->params;
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
