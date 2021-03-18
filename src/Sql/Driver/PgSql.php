<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Driver;

use PDO;
use P3\Db\Sql;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Driver\Feature\LimitSqlProvider;
use P3\Db\Sql\Params;
use P3\Db\Sql\Statement\Select;

use function implode;

/**
 * Postgre sql-driver
 */
class PgSql extends Driver implements LimitSqlProvider
{
    public function __construct(PDO $pdo = null)
    {
        parent::__construct($pdo, '"', '"', "'");
    }

    /**
     * PgSQL supports OFFSET without LIMIT
     *
     * {@inheritDoc}
     */
    public function getLimitSQL(Select $select, Params $params): string
    {
        $limit  = $select->limit;
        $offset = $select->offset;

        if (!isset($limit) && (int)$offset === 0) {
            return '';
        }

        $sqls = [];
        if (isset($limit)) {
            $limit = $params->create($limit, PDO::PARAM_INT, 'limit');
            $sqls[] = Sql::LIMIT . " {$limit}";
        }

        $offset = (int)$offset;
        if ($offset > 0) {
            $offset = $params->create($offset, PDO::PARAM_INT, 'offset');
            $sqls[] = Sql::OFFSET . " {$offset}";
        }

        return empty($sqls) ? '' : implode(" ", $sqls);
    }
}
