<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace pine3ree\Db\Sql\Driver\Feature;

use pine3ree\Db\Sql\Params;
use pine3ree\Db\Sql\Statement\Insert;

/**
 * Provides custom INSERT SQL strings in special cases like multi-row inserts
 */
interface InsertSqlProvider
{
    /**
     * Customize SQL-string for a sql Insert statement
     *
     * @param Insert $insert
     * @param Params $params The parameters collector
     */
    public function getInsertSQL(Insert $insert, Params $params): string;
}
