<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use P3\Db\Sql\Predicate\Between;

/**
 * This class represents a sql NOT BETWEEN condition
 */
class NotBetween extends Between
{
    protected static $not = true;
}
