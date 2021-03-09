<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Driver\Feature;

use P3\Db\Sql\Statement\Select;

/**
 * Interface SelectColumnsSqlProvider
 */
interface SelectColumnsSqlProvider
{
    public function getSelectColumnsSQL(Select $select, bool &$cache = true): string;
}
