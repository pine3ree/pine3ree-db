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
 * The default ANSI SQL Driver
 */
class Ansi extends Driver
{
    public function __construct()
    {
        parent::__construct(null, '"', '"', "'");
    }

    public function setPDO(PDO $pdo)
    {
        // do not use PDO for ANSI-SQL;
    }

    public function quoteValue($value): string
    {
        if (null === $value) {
            return 'NULL';
        }
        if (is_int($value)) {
            return (string)$value;
        }
        if (is_bool($value)) {
            return (string)(int)$value;
        }

        if (!is_string($value)) {
            $value = (string)$value;
        }

        return "'{$this->escape($value)}'";
    }

    /**
     * ANSI SQL does not support LIMIT/OFFSET, return a warnin string.
     *
     * @param Select $select
     * @return string
     */
    public function getLimitSQL(Select $select): string
    {
        $limit  = $select->limit;
        $offset = $select->offset;

        if (!isset($limit) && (int)$offset === 0) {
            return '';
        }

        if (isset($limit)) {
            $sql = Sql::LIMIT . " {$limit}";
        }

        $offset = (int)$offset;
        if ($offset > 0) {
            if (!isset($sql)) {
                $sql = Sql::LIMIT . " " . PHP_INT_MAX;
            }
            $sql .= " " . Sql::OFFSET . " {$offset}";
        }

        return $sql ?? "[UNSUPPORTED: $sql]";
    }
}
