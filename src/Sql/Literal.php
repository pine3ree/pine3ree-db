<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use P3\Db\Sql\Expression;

/**
 * This class represents a literal SQL expression without parameters
 */
class Literal extends Expression
{
    public function __construct(string $literal)
    {
        parent::__construct($literal);
    }
}
