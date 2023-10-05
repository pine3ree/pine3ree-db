<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\Db\Sql\Predicate;

use pine3ree\Db\Sql\Predicate\Between;

/**
 * This class represents a sql NOT BETWEEN condition
 */
class NotBetween extends Between
{
    /** @var bool */
    protected static $not = true;
}
