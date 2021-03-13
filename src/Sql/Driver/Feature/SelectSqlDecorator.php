<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Driver\Feature;

use P3\Db\Sql\Params;
use P3\Db\Sql\Statement\Select;

/**
 * Interface SelectSqlDecorator
 */
interface SelectSqlDecorator
{
    /**
     * Generate and decorate a select SQL when needed, for instance when providing
     * support for non-standard SQL features such as limit/offset.
     *
     * Even if it's not required, as we are using named parameter markers, the
     * original sql select string is generated internally to maintan the correct
     * ordering of imported parameters
     *
     * @param Select $select
     * @return string
     */
    public function decorateSelectSQL(Select $select, Params $params): string;
}
