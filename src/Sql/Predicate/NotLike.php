<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace pine3ree\Db\Sql\Predicate;

use pine3ree\Db\Sql\Predicate\Like;

/**
 * This class represents a sql NOT LIKE condition
 */
class NotLike extends Like
{
    protected static $not = true;
}
