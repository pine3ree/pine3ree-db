<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Driver;

use P3\Db\Sql\Driver;
use P3\Db\Sql\Driver\Feature\LimitSqlProvider;
use P3\Db\Sql\Statement\Select;
use PDO;
use RuntimeException;

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

    public function getLimitSQL(Select $select): string
    {
        $order  = $select->orderBy;
        $limit  = max(0, (int)$select->limit);
        $offset = max(0, (int)$select->offset);

        if (empty($order) && ($limit > 0 || $offset > 0)) {
            throw new RuntimeException(
                "Cannot apply limit/offset to `sqlsrv` without an ORDER-BY clause!"
            );
        }

        if ($offset > 0) {
            $offset = $select->createParam($offset, PDO::PARAM_INT, 'offset');
        }

        $offset_sql = "OFFSET ({$offset}) ROWS";

        if ($limit === 0) {
            return $offset_sql;
        }

        $fetch = $select->createParam($limit, PDO::PARAM_INT, 'fetch');
        $fetch_sql = $offset === 0
            ? "FETCH FIRST ({$fetch}) ROWS ONLY"
            : "FETCH NEXT ({$fetch}) ROWS ONLY";

        return "{$offset_sql} {$fetch_sql}";
    }
}
