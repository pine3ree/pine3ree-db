<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Driver;

use PDO;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Statement\Select;
use P3\Db\Sql\Statement\Insert;

use function strtoupper;

/**
 * Oci sql-driver
 */
class Oci extends Driver
{
    public function __construct(PDO  $pdo = null)
    {
        parent::__construct($pdo, '"', '"', "'");
    }

    public function quoteIdentifier(string $identifier): string
    {
        if ($identifier === '*') {
            return $identifier;
        }

        if ($this->isQuoted($identifier)) {
            return $identifier;
        }

        return parent::quoteIdentifier(strtoupper($identifier));
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
            $limit = isset($limit) ? ($limit + $offset) : PHP_INT_MAX;
            $limit_sql = "SELECT \"__oci_tb\".*, ROWNUM AS \"__oci_rn\" FROM ({$sql}) \"__oci_tb\" WHERE ROWNUM <= {$limit}";
            return "SELECT * FROM ($limit_sql) WHERE \"__oci_rn\" > {$offset}";
        }

        return $sql;
    }
}
