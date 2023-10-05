<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\Db\Sql;

use pine3ree\Db\Sql\Predicate\Expression as ExpressionPredicate;

/**
 * This abstract class represents a generic SQL Expression with parameters and
 * also a sql-expression-predicate
 */
class Expression extends ExpressionPredicate
{
}
