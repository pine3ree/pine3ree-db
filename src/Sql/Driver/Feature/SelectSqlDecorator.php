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
     * The original sql select string MUST be generated internally to maintan
     * the correct parameters ordering
     *
     * @param Select $select The SQL select statement object
     * @param Params $params The parameters collector
     * @param string $sep An optional custome non-empty separator string (space/new-line, ...)
     * @return string The decorated SQL-string
     */
    public function decorateSelectSQL(Select $select, Params $params, string $sep = null): string;
}
