<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\Db\Sql\Predicate;

use pine3ree\Db\Sql\Predicate\IsUnknown;

/**
 * This class represents a sql IS NOT UNKNOWN predicate
 */
class IsNotUnknown extends IsUnknown
{
    protected static $not = true;
}
