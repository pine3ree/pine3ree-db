<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Driver;

use P3\Db\Sql\Driver;

use P3\Db\Sql\Statement\Select;
use P3\Db\Sql\Statement\Insert;

/**
 * Oci sql-driver
 */
class Oci extends Driver
{
    public function __construct()
    {
        parent::__construct('"', '"', "'");
    }

    public function getLimitSQL(int $limit = null, int $offset = null): string
    {
        return '';
    }

    public function decorateSelectSQL(Select $select, string $sql): string
    {
        $limit  = $select->limit;
        $offset = $select->offset;

        if (isset($limit) && !isset($offset)) {
            return "SELECT * FROM ({$sql}) WHERE ROWNUM <= {$limit}";
        }

        if (isset($offset) && $offset > 0) {
            if (!isset($limit)) {
                $limit = PHP_INT_MAX;
            }
            $limit_sql = "SELECT *, ROWNUM AS __rownum FROM ({$sql}) WHERE ROWNUM <= {$limit}";
            return "SELECT * FROM ($limit_sql) WHERE __rownum > {$offset}";
        }

        return $sql;
    }
}
