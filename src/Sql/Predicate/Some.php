<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\Db\Sql\Predicate;

use pine3ree\Db\Sql;
use pine3ree\Db\Sql\Predicate\QuantifiedComparison;

/**
 * This class represents a sql operator-SOME(SELECT...) condition
 */
class Some extends QuantifiedComparison
{
    protected static string $quantifier = Sql::SOME;
}
