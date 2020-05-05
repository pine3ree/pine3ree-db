<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Driver;

use P3\Db\Sql\Driver;

/**
 * MySql sql-driver
 */
class MySql extends Driver
{
    public function __construct()
    {
        parent::__construct('`', '`', "'");
    }

    public function getLimitSQL(int $limit = null, int $offset = null): string
    {
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
