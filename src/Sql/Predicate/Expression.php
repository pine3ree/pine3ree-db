<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use InvalidArgumentException;
use P3\Db\Sql\Predicate;
use P3\Db\Sql\Traits\ExpressionTrait;

use function trim;

/**
 * This class represents a sql expression with parameter markers
 */
class Expression extends Predicate
{
    use ExpressionTrait;
}
