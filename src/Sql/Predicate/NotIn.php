<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use P3\Db\Sql\Predicate\In;

/**
 * This class represents a sql NOT IN condition
 */
class NotIn extends In
{
    protected static $not = true;
}
