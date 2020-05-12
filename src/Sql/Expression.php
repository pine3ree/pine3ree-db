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
}
