<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use P3\Db\Sql\Predicate;
use P3\Db\Sql\Traits\LiteralTrait;

/**
 * This class represents a sql literal-expression predicate
 */
class Literal extends Predicate
{
    use LiteralTrait;
}
