<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace pine3ree\Db\Sql\Clause;

use pine3ree\Db\Sql;
use pine3ree\Db\Sql\Clause\ConditionalClause;

/**
 * Represents a SQL "HAVING" clause
 */
class Having extends ConditionalClause
{
    protected static $name = Sql::HAVING;
}
