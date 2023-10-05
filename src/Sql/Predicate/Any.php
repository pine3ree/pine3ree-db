<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\Db\Sql\Predicate;

use pine3ree\Db\Sql;
use pine3ree\Db\Sql\Predicate\CompareTo;

/**
 * This class represents a sql operator-ANY(SELECT...) condition
 */
class Any extends CompareTo
{
    /** @var string */
    protected static $quantifier = Sql::ANY;
}
