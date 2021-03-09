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
    /**
     * Customize SQL-string for a sql Select statement's columns
     *
     * @param Select $select
     * @param bool $cache Flag tat will be set to true if generated sql can be cached
     * @return string
     */
    public function getSelectColumnsSQL(Select $select, bool &$cache = true): string;
}
