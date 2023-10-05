<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\Db\Sql\Predicate;

use pine3ree\Db\Sql\Predicate\Exists;
use pine3ree\Db\Sql\Statement\Select;

/**
 * This class represents a sql NOT EXISTS condition
 */
class NotExists extends Exists
{
    /** @var bool */
    protected static $not = true;
}
