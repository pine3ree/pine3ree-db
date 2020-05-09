<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use P3\Db\Sql\Element;

use function trim;

/**
 * This class represents a literal SQL expression without parameters
 */
class Literal extends Element
{
    public function __construct(string $literal)
    {
        $this->sql = trim($literal);
    }
}
