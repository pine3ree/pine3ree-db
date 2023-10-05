<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace pine3ree\Db\Sql\Predicate;

use pine3ree\Db\Sql\Predicate\IsNull;

/**
 * This class represents a sql IS NOT NULL predicate
 */
class IsNotNull extends IsNull
{
    protected static $not = true;
}
