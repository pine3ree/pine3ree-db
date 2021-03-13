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
use P3\Db\Exception\RuntimeException;

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
            $offset = $this->createParam($select, $offset, PDO::PARAM_INT, 'offset');
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

        $fetch = $this->createParam($select, $limit, PDO::PARAM_INT, 'fetch');
        $fetch_sql = $offset === 0
            ? "FETCH FIRST {$fetch} ROWS ONLY"
            : "FETCH NEXT {$fetch} ROWS ONLY";

        return "{$offset_sql} {$fetch_sql}";
    }
}
