<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace pine3ree\Db\Sql\Clause;

use pine3ree\Db\Sql;
use pine3ree\Db\Sql\Clause\ConditionalClause;

/**
 * Represents a SQL "ON" clause
 */
class On extends ConditionalClause
{
    protected static string $name = Sql::ON;

    protected static bool $useParenthesis = true;
}
