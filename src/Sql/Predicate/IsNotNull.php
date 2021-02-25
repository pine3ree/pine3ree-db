<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use P3\Db\Sql\Predicate\IsNull;

/**
 * This class represents a sql IS NOT NULL predicate
 */
class IsNotNull extends IsNull
{
    protected static $not = true;
}
