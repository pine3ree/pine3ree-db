<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use P3\Db\Sql\Predicate\CompareTo;

/**
 * This class represents a sql operator-ALL(SELECT...) condition
 */
class All extends CompareTo
{
    /** @var string */
    protected static $quantifier = 'ALL';
}
