<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\Db\Sql\Clause;

use pine3ree\Db\Sql;
use pine3ree\Db\Sql\Clause\ConditionalClause;

/**
 * Represents a SQL "WHERE" clause
 */
class Where extends ConditionalClause
{
    protected static string $name = Sql::WHERE;
}
