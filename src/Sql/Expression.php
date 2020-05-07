<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use P3\Db\Sql\Element;

use function trim;

/**
 * This abstract class represents a generic SQL Expression with parameters
 */
class Expression extends Element
{
    public function __construct(string $expression, array $params = [])
    {
        $this->sql = trim($expression);
        foreach ($params as $key => $value) {
            $this->setParam($key, $value);
        }
    }
}
