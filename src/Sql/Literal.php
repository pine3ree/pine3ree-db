<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\Db\Sql;

use pine3ree\Db\Sql\Predicate\Literal as LiteralPredicate;

/**
 * This class represents a literal SQL expression without parameters and also a
 * sql-literal-predicate
 */
class Literal extends LiteralPredicate
{
}
