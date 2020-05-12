<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Clause;

use P3\Db\Sql;
use P3\Db\Sql\Clause\ConditionalClause;

/**
 * Represents a SQL "WHERE" clause
 */
class Where extends ConditionalClause
{
    protected static $name = Sql::WHERE;
}
