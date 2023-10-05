<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\Db\Sql\Predicate;

use pine3ree\Db\Sql\Predicate\In;

/**
 * This class represents a sql NOT IN condition
 */
class NotIn extends In
{
    protected static $not = true;
}
