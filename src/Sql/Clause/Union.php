<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\Db\Sql\Clause;

use pine3ree\Db\Sql;
use pine3ree\Db\Sql\Clause\Combine;

/**
 * Represents a SQL "UNION" clause
 */
class Union extends Combine
{
    protected static string $name = Sql::UNION;
}
