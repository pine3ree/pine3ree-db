<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use P3\Db\Sql\Predicate\RegExp;

/**
 * This class represents a sql "~*" or "!~*" condition
 */
class NotRegExp extends RegExp
{
    protected static $not = true;
}
