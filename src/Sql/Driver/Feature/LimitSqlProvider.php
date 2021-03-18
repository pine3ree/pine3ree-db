<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Driver\Feature;

use P3\Db\Sql\Params;
use P3\Db\Sql\Statement\Select;

/**
 * Interface LimitSqlProvider provide an interface for creating a customization
 * of LIMIT/OFFSET non-ansi sql clauses, if supported by the driver sql-platform.
 *
 * It may also provide an equivalent implementation as the OFFSET...FETCH clauses in mssql
 */
interface LimitSqlProvider
{
    public function getLimitSQL(Select $select, Params $params, string $sep = " "): string;
}
