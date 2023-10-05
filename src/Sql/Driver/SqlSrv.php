<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\Db\Sql\Driver;

use pine3ree\Db\Sql\Driver;
use pine3ree\Db\Sql\Driver\Feature\LimitSqlProvider;
use pine3ree\Db\Sql\Params;
use pine3ree\Db\Sql\Statement\Select;
use PDO;
use pine3ree\Db\Exception\RuntimeException;

use function max;

/**
 * SqlSrv sql-driver: WIP
 */
class SqlSrv extends Driver implements LimitSqlProvider
{
    public function __construct(PDO $pdo = null)
    {
        parent::__construct($pdo, '[', ']', "'");
    }

    /**
     * For mssql the limit clause is achieved via OFFSET...FETCH clauses for
     * the ORDER BY clause.
     *
     * @throws RuntimeException
     */
    public function getLimitSQL(Select $select, Params $params): string
    {
        $limit  = $select->limit;
        $offset = max(0, (int)$select->offset);

        if (!isset($limit) && $offset === 0) {
            return '';
        }

        $orderBy = $select->orderBy;
        if (empty($orderBy)) {
            throw new RuntimeException(
                "Cannot apply limit/offset to `sqlsrv` without an ORDER-BY clause!"
            );
        }

        if ($offset > 0) {
            $offset = $params->create($offset, PDO::PARAM_INT, 'offset');
        }

        $offset_sql = "OFFSET {$offset} ROWS";

        if (!isset($limit)) {
            return $offset_sql;
        }

        if ($limit === 0) {
            throw new RuntimeException(
                "The number of rows provided for a FETCH clause must be greater"
                . " than zero, `{$limit}` provided!"
            );
        }

        $fetch = $params->create($limit, PDO::PARAM_INT, 'fetch');
        $fetch_sql = $offset === 0
            ? "FETCH FIRST {$fetch} ROWS ONLY"
            : "FETCH NEXT {$fetch} ROWS ONLY";

        return "{$offset_sql} {$fetch_sql}";
    }
}
