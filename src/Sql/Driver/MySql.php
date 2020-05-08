<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Driver;

use PDO;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Statement\Select;

/**
 * MySql sql-driver
 */
class MySql extends Driver
{
    public function __construct(PDO $pdo = null)
    {
        parent::__construct($pdo, '`', '`', "'");
    }

    public function getLimitSQL(Select $select): string
    {
        $limit  = $select->limit;
        $loffset= $select->offset;

        if (!isset($limit) && !isset($limit)) {
            return '';
        }

        if (isset($limit)) {
            $sql = "LIMIT {$limit}";
        }

        $offset = (int)$offset;
        if ($offset > 0) {
            if (!isset($sql)) {
                $sql = "LIMIT " . PHP_INT_MAX;
            }
            $sql .= " OFFSET {$offset}";
        }

        return $sql ?? '';
    }
}
