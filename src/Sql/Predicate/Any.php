<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use P3\Db\Sql\Predicate\CompareTo;

/**
 * This class represents a sql operator-ANY(SELECT...) condition
 */
class Any extends CompareTo
{
    /** @var string */
    protected static $quantifier = 'ANY';
}
