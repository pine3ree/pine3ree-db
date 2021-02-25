<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use P3\Db\Sql\Predicate\Is;

/**
 * This class represents a sql IS NOT predicate with the SQL values NULL, TRUE,
 * FALSE and UNKNOWN
 */
class IsNot extends Is
{
    protected static $not = true;
}
