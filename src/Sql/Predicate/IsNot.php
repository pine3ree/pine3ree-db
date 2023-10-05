<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\Db\Sql\Predicate;

use pine3ree\Db\Sql\Predicate\Is;

/**
 * This class represents a sql IS NOT predicate with the SQL values NULL, TRUE,
 * FALSE and UNKNOWN
 */
class IsNot extends Is
{
    protected static $not = true;
}
