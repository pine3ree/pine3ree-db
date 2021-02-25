<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use P3\Db\Sql\Predicate\Like;

/**
 * This class represents a sql NOT LIKE condition
 */
class NotLike extends Like
{
    protected static $not = true;
}
