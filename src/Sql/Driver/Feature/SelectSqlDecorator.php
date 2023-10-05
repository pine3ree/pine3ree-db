<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace pine3ree\Db\Sql\Driver\Feature;

use pine3ree\Db\Sql\Params;
use pine3ree\Db\Sql\Statement\Select;

/**
 * Interface SelectSqlDecorator
 */
interface SelectSqlDecorator
{
    /**
     * Generate and decorate a select SQL when needed, for instance when providing
     * support for non-standard SQL features such as limit/offset.
     *
     * The original sql select string MUST be generated internally to maintan
     * the correct parameters ordering
     *
     * @param Select $select The SQL select statement object
     * @param Params $params The parameters collector
     * @param bool $pretty output a nicely formatted SQL string
     * @return string The decorated SQL-string
     */
    public function decorateSelectSQL(Select $select, Params $params, bool $pretty = false): string;
}
