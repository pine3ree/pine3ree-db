<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace pine3ree\Db\Sql\Driver\Feature;

use pine3ree\Db\Sql\Params;
use pine3ree\Db\Sql\Statement\Select;

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
    public function getSelectColumnsSQL(Select $select, Params $params, bool &$cache = true): string;
}
