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
    /**
     * @const string Quoted table alias for LIMIT+OFFSET statements
     */
    public const TB = '"__oci_tb"';

    /**
     * @const string Quoted ROWNUM alias for LIMIT+OFFSET statements
     */
    public const RN = '"__oci_rn"';

    public function __construct(PDO $pdo = null)
    {
        parent::__construct($pdo, '"', '"', "'");
    }

    /**
     * {@inheritDoc}
     *
     * Quoting Oracle identifiers may introduce errors as Oracle creates uppercase
     * table and column names if not quoted themself on creation.
     */
    public function quoteIdentifier(string $identifier): string
    {
        if ($identifier === '*' || empty($this->qlr) || $this->isQuoted($identifier)) {
            return $identifier;
        }

        // table and column names starting with the underscore char must be quoted
        if ('_' === substr($identifier, 0, 1)) {
            return parent::quoteIdentifier($identifier);
        }

        return $identifier;
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
            $tb = self::TB;
            $rn = self::RN;
            $limit = isset($limit) ? ($limit + $offset) : PHP_INT_MAX;
            $limit_sql = "SELECT {$tb}.*, ROWNUM AS {$rn} FROM ({$sql}) {$tb} WHERE ROWNUM <= {$limit}";
            return "SELECT * FROM ($limit_sql) WHERE {$rn} > {$offset}";
        }

        return $sql;
    }
}
