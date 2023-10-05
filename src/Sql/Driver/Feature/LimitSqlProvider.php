<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace pine3ree\Db\Sql\Driver\Feature;

use pine3ree\Db\Sql\Params;
use pine3ree\Db\Sql\Statement\Select;

/**
 * Interface LimitSqlProvider provide an interface for creating a customization
 * of LIMIT/OFFSET non-ansi sql clauses, if supported by the driver sql-platform.
 *
 * It may also provide an equivalent implementation as the OFFSET...FETCH clauses in mssql
 */
interface LimitSqlProvider
{
    /**
     * Create/mimic a custom LIMIT/OFFSET clause for given Select statement
     *
     * @param Select $select
     * @param Params $params
     * @return string
     */
    public function getLimitSQL(Select $select, Params $params): string;
}
