<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use InvalidArgumentException;
use P3\Db\Sql\Element;
use P3\Db\Sql\Traits\ExpressionTrait;

use function trim;

/**
 * This abstract class represents a generic SQL Expression with parameters
 */
class Expression extends Element
{
    use ExpressionTrait;

    /**
     * @param string $expression The SQL expression with optional {named} placeholders
     * @param array $params A list of parameters for the expression indexed by placeholder
     * @throws InvalidArgumentException
     */
    public function __construct(string $expression, array $params = [])
    {
        $expression = trim($expression);
        if ('' === $expression) {
            throw new InvalidArgumentException(
                "A SQL-expression cannot be empty!"
            );
        }
        $this->expression = $expression;
        if (!empty($params)) {
            foreach ($params as $key => $value) {
                $this->setParam($key, $value);
            }
        }
    }
}
