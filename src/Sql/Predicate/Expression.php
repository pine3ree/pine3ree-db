<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use InvalidArgumentException;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Predicate;
use P3\Db\Sql\Traits\ExpressionTrait;

use function trim;

/**
 * This class represents a sql expression with parameter markers
 */
class Expression extends Predicate
{
    use ExpressionTrait;

    public function __construct(string $expression, array $params = [])
    {
        $sql = trim($expression);
        if ('' === $sql) {
            throw new InvalidArgumentException(
                "A SQL Expression predicate cannot be empty!"
            );
        }
        $this->expression = $expression;
        if (!empty($params)) {
            foreach (array_values($params) as $i => $value) {
                $this->setParam($i, $value);
            }
        }
    }
}
