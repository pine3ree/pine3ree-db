<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Driver\Feature;

use P3\Db\Sql\Params;
use P3\Db\Sql\Statement\Select;

/**
 * Interface LimitSqlProvider
 */
interface LimitSqlProvider
{
    public function getLimitSQL(Select $select, Params $params): string;
}
